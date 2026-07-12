"""End-to-end tests for the newsletter double opt-in lifecycle:
subscribe → confirm → welcome, plus unsubscribe / resubscribe and edge cases.
"""
import pytest
from django.urls import reverse

from apps.newsletter.models import EmailQueue, Subscriber

SUBSCRIBE = 'articles_frontend:newsletter_subscribe'
CONFIRM = 'articles_frontend:newsletter_confirm'
UNSUB = 'articles_frontend:unsubscribe'
RESUB = 'articles_frontend:resubscribe'


def _subscribe(client, email, name=''):
    return client.post(reverse(SUBSCRIBE), {'email': email, 'name': name})


@pytest.mark.django_db
class TestSubscribeDoubleOptIn:
    def test_subscribe_creates_pending_and_queues_confirmation(self, client):
        resp = _subscribe(client, 'a@example.com', 'Al')
        assert resp.status_code == 200
        assert resp.json()['status'] == 'pending_confirmation'
        s = Subscriber.objects.get(email='a@example.com')
        assert s.status == 'pending'
        assert s.name == 'Al'
        assert s.confirm_token and s.unsub_token
        assert EmailQueue.objects.filter(
            to_email='a@example.com', subject__icontains='Confirm').exists()

    def test_email_is_lowercased_and_validated(self, client):
        _subscribe(client, 'MixedCase@Example.COM')
        assert Subscriber.objects.filter(email='mixedcase@example.com').exists()

    def test_invalid_email_rejected(self, client):
        resp = _subscribe(client, 'not-an-email')
        assert resp.status_code == 400
        assert not Subscriber.objects.exists()

    def test_missing_email_rejected(self, client):
        resp = client.post(reverse(SUBSCRIBE), {})
        assert resp.status_code == 400

    def test_get_not_allowed(self, client):
        assert client.get(reverse(SUBSCRIBE)).status_code == 405


@pytest.mark.django_db
class TestConfirm:
    def test_confirm_activates_and_sends_welcome(self, client):
        _subscribe(client, 'b@example.com')
        s = Subscriber.objects.get(email='b@example.com')
        resp = client.get(reverse(CONFIRM, args=[s.confirm_token]))
        assert resp.status_code == 200
        s.refresh_from_db()
        assert s.status == 'active'
        assert s.confirmed_at is not None
        assert s.confirm_token == ''  # single-use
        assert EmailQueue.objects.filter(
            to_email='b@example.com', subject__icontains='Welcome').exists()

    def test_confirm_invalid_token(self, client):
        resp = client.get(reverse(CONFIRM, args=['bogus-token']))
        assert resp.status_code == 200
        assert b'expired' in resp.content.lower() or b'invalid' in resp.content.lower()

    def test_confirm_twice_is_safe(self, client):
        _subscribe(client, 'c@example.com')
        s = Subscriber.objects.get(email='c@example.com')
        tok = s.confirm_token
        client.get(reverse(CONFIRM, args=[tok]))
        # token cleared; second hit with the old token is treated as invalid, not a crash
        resp = client.get(reverse(CONFIRM, args=[tok]))
        assert resp.status_code == 200

    def test_resubscribe_active_is_idempotent(self, client):
        _subscribe(client, 'd@example.com')
        s = Subscriber.objects.get(email='d@example.com')
        client.get(reverse(CONFIRM, args=[s.confirm_token]))
        resp = _subscribe(client, 'd@example.com')
        assert resp.json()['status'] == 'already_subscribed'


@pytest.mark.django_db
class TestUnsubscribeResubscribe:
    def _active(self, client, email):
        _subscribe(client, email)
        s = Subscriber.objects.get(email=email)
        client.get(reverse(CONFIRM, args=[s.confirm_token]))
        s.refresh_from_db()
        return s

    def test_unsubscribe_get_shows_confirm(self, client):
        s = self._active(client, 'e@example.com')
        resp = client.get(reverse('articles_frontend:unsubscribe_token', args=[s.unsub_token]))
        assert resp.status_code == 200
        assert b'Unsubscribe?' in resp.content
        s.refresh_from_db()
        assert s.status == 'active'  # GET must not opt out

    def test_unsubscribe_post_opts_out(self, client):
        s = self._active(client, 'f@example.com')
        resp = client.post(reverse(UNSUB), {'token': s.unsub_token})
        assert resp.status_code == 200
        s.refresh_from_db()
        assert s.status == 'unsubscribed'
        assert s.unsubscribed_at is not None

    def test_resubscribe_reactivates(self, client):
        s = self._active(client, 'g@example.com')
        client.post(reverse(UNSUB), {'token': s.unsub_token})
        resp = client.get(reverse(RESUB, args=[s.unsub_token]))
        assert resp.status_code == 200
        s.refresh_from_db()
        assert s.status == 'active'

    def test_resubscribe_via_subscribe_reconfirms(self, client):
        s = self._active(client, 'h@example.com')
        client.post(reverse(UNSUB), {'token': s.unsub_token})
        resp = _subscribe(client, 'h@example.com')
        assert resp.json()['status'] == 'pending_confirmation'
        s.refresh_from_db()
        assert s.status == 'pending'

    def test_unsubscribe_invalid_token(self, client):
        resp = client.get(reverse('articles_frontend:unsubscribe_token', args=['nope']))
        assert resp.status_code == 200
        assert b'invalid' in resp.content.lower() or b'expired' in resp.content.lower()


@pytest.mark.django_db
class TestTopicNotifications:
    def _article(self, category):
        from django.utils import timezone
        from apps.articles.models import Article
        return Article.objects.create(
            title='Parliament debates budget', slug='parliament-budget',
            excerpt='A budget story.', content='<p>body</p>' * 5,
            status='published', published_at=timezone.now(), category=category,
        )

    def test_category_followers_notified(self, category):
        from apps.articles.models import TopicFollow
        from apps.newsletter.tasks import notify_topic_followers
        art = self._article(category)
        TopicFollow.objects.create(email='fan@example.com', follow_type='category',
                                   follow_id=category.id)
        notify_topic_followers(str(art.id))
        q = EmailQueue.objects.filter(to_email='fan@example.com')
        assert q.count() == 1
        assert 'New in Politics' in q.first().subject
        # unfollow link present in the email
        assert '/topics/unfollow/' in q.first().body_html

    def test_follower_deduplicated_across_category_and_tag(self, category):
        from apps.articles.models import Tag, TopicFollow
        from apps.newsletter.tasks import notify_topic_followers
        art = self._article(category)
        tag = Tag.objects.create(name='Budget', slug='budget', type='topic')
        art.tags.add(tag)
        TopicFollow.objects.create(email='dup@example.com', follow_type='category', follow_id=category.id)
        TopicFollow.objects.create(email='dup@example.com', follow_type='tag', follow_id=tag.id)
        notify_topic_followers(str(art.id))
        assert EmailQueue.objects.filter(to_email='dup@example.com').count() == 1

    def test_unfollow_token_deletes_follow(self, client, category):
        from django.urls import reverse
        from apps.articles.models import TopicFollow
        f = TopicFollow.objects.create(email='bye@example.com', follow_type='category',
                                       follow_id=category.id)
        assert f.unfollow_token  # auto-generated on save
        resp = client.get(reverse('articles_frontend:topic_unfollow', args=[f.unfollow_token]))
        assert resp.status_code == 200
        assert not TopicFollow.objects.filter(pk=f.pk).exists()


@pytest.mark.django_db
class TestCampaignAndContact:
    def test_campaign_wraps_with_unsubscribe_footer(self):
        from apps.newsletter.models import NewsletterIssue, Subscriber
        from apps.newsletter.services.mailer import send_campaign
        sub = Subscriber.objects.create(email='r@example.com', unsub_token='tok123', status='active')
        issue = NewsletterIssue.objects.create(subject='Weekly Roundup', content='<p>Hello</p>')
        item = send_campaign(sub, issue)
        assert item.subject == 'Weekly Roundup'
        assert 'Unsubscribe' in item.body_html
        assert 'tok123' in item.body_html

    def test_contact_form_queues_admin_alert(self, client, user_admin):
        from django.urls import reverse
        resp = client.post(reverse('articles_frontend:contact'), {
            'name': 'Tipster', 'email': 'tip@example.com',
            'subject': 'Scoop', 'message': 'Big news downtown.',
        })
        assert resp.status_code == 200
        alert = EmailQueue.objects.filter(subject__icontains='Contact')
        assert alert.exists()
        assert alert.first().to_email == user_admin.email
        assert 'Big news downtown.' in alert.first().body_html
