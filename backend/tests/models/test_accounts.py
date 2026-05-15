import pytest
from apps.accounts.models import User, Role


class TestRole:
    def test_has_permission_wildcard(self, role_super_admin):
        assert role_super_admin.has_permission('anything.here') is True

    def test_has_permission_specific(self, role_author):
        assert role_author.has_permission('articles.own') is True
        assert role_author.has_permission('articles.delete') is False

    def test_has_permission_media_upload(self, role_author):
        assert role_author.has_permission('media.upload') is True

    def test_has_permission_editor_wildcard_pattern(self, role_editor):
        # Editor has 'articles.*' but that is a literal string, not a glob
        # It should match exactly 'articles.*' not arbitrary sub-keys
        assert role_editor.has_permission('articles.*') is True
        assert role_editor.has_permission('comments.moderate') is True

    def test_has_permission_empty_list(self, db):
        role = Role.objects.create(name='empty', level=0, permissions=[])
        assert role.has_permission('anything') is False

    def test_has_permission_non_list(self, db):
        role = Role.objects.create(name='broken', level=0, permissions='not a list')
        assert role.has_permission('anything') is False

    def test_system_role_levels(self):
        assert Role.SYSTEM_ROLES['author'] == 1
        assert Role.SYSTEM_ROLES['editor'] == 2
        assert Role.SYSTEM_ROLES['super_admin'] == 3

    def test_str(self, role_author):
        assert str(role_author) == 'author'

    def test_ordering(self, role_author, role_editor, role_super_admin):
        roles = list(Role.objects.all())
        levels = [r.level for r in roles]
        assert levels == sorted(levels)

    def test_name_unique(self, role_author, db):
        with pytest.raises(Exception):
            Role.objects.create(name='author', level=99, permissions=[])

    def test_uuid_primary_key(self, role_author):
        import uuid
        assert isinstance(role_author.pk, uuid.UUID)


class TestUser:
    def test_create_user(self, db):
        user = User.objects.create_user(
            username='test', email='test@test.com', password='pass123'
        )
        assert user.email == 'test@test.com'
        assert user.check_password('pass123')

    def test_get_role_level(self, user_author, user_admin):
        assert user_author.get_role_level() == 1
        assert user_admin.get_role_level() == 3

    def test_get_role_level_editor(self, user_editor):
        assert user_editor.get_role_level() == 2

    def test_has_role_permission(self, user_admin):
        assert user_admin.has_role_permission('anything') is True

    def test_has_role_permission_author(self, user_author):
        assert user_author.has_role_permission('articles.own') is True
        assert user_author.has_role_permission('system.admin') is False

    def test_no_role_returns_zero(self, db):
        user = User.objects.create_user(
            username='norole', email='norole@test.com', password='pass'
        )
        assert user.get_role_level() == 0

    def test_no_role_permission_returns_false(self, db):
        user = User.objects.create_user(
            username='norole2', email='norole2@test.com', password='pass'
        )
        assert user.has_role_permission('anything') is False

    def test_str_display_name(self, user_author):
        assert str(user_author) == 'Test Author'

    def test_str_falls_back_to_email(self, db):
        user = User.objects.create_user(
            username='fallback', email='fallback@test.com', password='pass'
        )
        assert str(user) == 'fallback@test.com'

    def test_email_is_username_field(self):
        assert User.USERNAME_FIELD == 'email'

    def test_email_unique(self, user_author, db):
        with pytest.raises(Exception):
            User.objects.create_user(
                username='dup', email='author@example.com', password='pass'
            )

    def test_uuid_primary_key(self, user_author):
        import uuid
        assert isinstance(user_author.pk, uuid.UUID)
