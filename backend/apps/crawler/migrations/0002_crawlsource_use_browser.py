from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('crawler', '0001_initial'),
    ]

    operations = [
        migrations.AddField(
            model_name='crawlsource',
            name='use_browser',
            field=models.BooleanField(default=False, help_text='Auto-detected: use Selenium for sources that block HTTP'),
        ),
    ]
