"""Make the Local News category description location-neutral.

The old copy ("...from Northern Uganda and the Lango sub-region") was too
specific — a reader in Kampala should feel included. Idempotent: only touches
the row if it still carries the old description.
"""
from django.db import migrations

OLD = 'Local reporting and community news from Northern Uganda and the Lango sub-region.'
NEW = 'Community news, events, and reporting from your part of the country.'


def forwards(apps, schema_editor):
    Category = apps.get_model('articles', 'Category')
    Category.objects.filter(slug='local-news', description=OLD).update(description=NEW)


def backwards(apps, schema_editor):
    Category = apps.get_model('articles', 'Category')
    Category.objects.filter(slug='local-news', description=NEW).update(description=OLD)


class Migration(migrations.Migration):

    dependencies = [
        ('articles', '0006_article_geo_place_article_latitude_article_longitude'),
    ]

    operations = [
        migrations.RunPython(forwards, backwards),
    ]
