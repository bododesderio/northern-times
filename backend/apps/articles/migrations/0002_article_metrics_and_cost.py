"""Add word_count, pull_quotes, keywords, rewrite_tokens_used, rewrite_cost to Article."""
import django.db.models
from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('articles', '0001_initial'),
    ]

    operations = [
        migrations.AddField(
            model_name='article',
            name='word_count',
            field=models.IntegerField(default=0),
        ),
        migrations.AddField(
            model_name='article',
            name='pull_quotes',
            field=models.JSONField(blank=True, default=list),
        ),
        migrations.AddField(
            model_name='article',
            name='keywords',
            field=models.JSONField(blank=True, default=list),
        ),
        migrations.AddField(
            model_name='article',
            name='rewrite_tokens_used',
            field=models.IntegerField(default=0),
        ),
        migrations.AddField(
            model_name='article',
            name='rewrite_cost',
            field=models.DecimalField(decimal_places=5, default=0, max_digits=8),
        ),
    ]
