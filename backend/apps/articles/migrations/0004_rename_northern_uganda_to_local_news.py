"""Rename the "Northern Uganda" category to location-based "Local News".

Renames both the display label and the slug on any existing row. Articles link
to Category by FK, so the relationship follows automatically. Reversible.
Idempotent — guards on the old slug so re-running (or running on a fresh seed
already using the new slug) is a no-op.
"""
from django.db import migrations

OLD_SLUG = 'northern-uganda'
NEW_SLUG = 'local-news'
NEW_NAME = 'Local News'
OLD_NAME = 'Northern Uganda'
NEW_DESC = 'Local reporting and community news from Northern Uganda and the Lango sub-region.'


def _rename(apps, slug_from, slug_to, name_to, desc_to):
    Category = apps.get_model('articles', 'Category')
    cat = Category.objects.filter(slug=slug_from).first()
    if not cat:
        return
    # Don't collide with an existing target row (defensive).
    if Category.objects.filter(slug=slug_to).exclude(pk=cat.pk).exists():
        return
    cat.slug = slug_to
    cat.name = name_to
    if desc_to is not None:
        cat.description = desc_to
    cat.save(update_fields=['slug', 'name', 'description'])


def forwards(apps, schema_editor):
    _rename(apps, OLD_SLUG, NEW_SLUG, NEW_NAME, NEW_DESC)


def backwards(apps, schema_editor):
    _rename(apps, NEW_SLUG, OLD_SLUG, OLD_NAME, None)


class Migration(migrations.Migration):

    dependencies = [
        ('articles', '0003_article_search_vector_and_more'),
    ]

    operations = [
        migrations.RunPython(forwards, backwards),
    ]
