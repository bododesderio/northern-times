# Add popup display, styling, button, frequency, targeting, and versioning fields.

from django.db import migrations, models


class Migration(migrations.Migration):

    dependencies = [
        ('ads', '0001_initial'),
    ]

    operations = [
        # Content field: allow blank (was required)
        migrations.AlterField(
            model_name='popup',
            name='content',
            field=models.TextField(blank=True, default=''),
        ),
        # Display fields
        migrations.AddField(
            model_name='popup',
            name='title',
            field=models.CharField(blank=True, default='', max_length=255),
        ),
        migrations.AddField(
            model_name='popup',
            name='body',
            field=models.TextField(blank=True, default=''),
        ),
        migrations.AddField(
            model_name='popup',
            name='image_url',
            field=models.URLField(blank=True, default='', max_length=500),
        ),
        migrations.AddField(
            model_name='popup',
            name='banner_style',
            field=models.CharField(
                choices=[
                    ('card_modal', 'Card Modal'),
                    ('minimal_bar', 'Minimal Bar'),
                    ('split_image', 'Split Image'),
                    ('fullscreen', 'Fullscreen'),
                    ('slide_in', 'Slide In'),
                    ('floating', 'Floating'),
                ],
                default='card_modal',
                max_length=20,
            ),
        ),
        migrations.AddField(
            model_name='popup',
            name='position',
            field=models.CharField(
                choices=[
                    ('center', 'Center'),
                    ('top_left', 'Top Left'),
                    ('top_right', 'Top Right'),
                    ('bottom_left', 'Bottom Left'),
                    ('bottom_right', 'Bottom Right'),
                    ('bottom_center', 'Bottom Center'),
                ],
                default='center',
                max_length=20,
            ),
        ),
        # Styling
        migrations.AddField(
            model_name='popup',
            name='bg_color',
            field=models.CharField(blank=True, default='#ffffff', max_length=30),
        ),
        migrations.AddField(
            model_name='popup',
            name='text_color',
            field=models.CharField(blank=True, default='#1a1a1a', max_length=30),
        ),
        migrations.AddField(
            model_name='popup',
            name='overlay_opacity',
            field=models.CharField(blank=True, default='0.5', max_length=10),
        ),
        # Buttons
        migrations.AddField(
            model_name='popup',
            name='button_text',
            field=models.CharField(blank=True, default='', max_length=100),
        ),
        migrations.AddField(
            model_name='popup',
            name='button_url',
            field=models.URLField(blank=True, default='', max_length=500),
        ),
        migrations.AddField(
            model_name='popup',
            name='btn_bg_color',
            field=models.CharField(blank=True, default='#cc0000', max_length=30),
        ),
        migrations.AddField(
            model_name='popup',
            name='btn_text_color',
            field=models.CharField(blank=True, default='#ffffff', max_length=30),
        ),
        migrations.AddField(
            model_name='popup',
            name='secondary_btn_text',
            field=models.CharField(blank=True, default='', max_length=100),
        ),
        migrations.AddField(
            model_name='popup',
            name='has_email_field',
            field=models.BooleanField(default=False),
        ),
        # Trigger (update choices on existing field)
        migrations.AlterField(
            model_name='popup',
            name='trigger_type',
            field=models.CharField(
                choices=[
                    ('page_load', 'Page Load'),
                    ('time_delay', 'Time Delay'),
                    ('scroll_percent', 'Scroll Percentage'),
                    ('exit_intent', 'Exit Intent'),
                    ('page_views', 'Page View Count'),
                ],
                default='page_load',
                max_length=50,
            ),
        ),
        migrations.AddField(
            model_name='popup',
            name='show_delay',
            field=models.IntegerField(default=0, help_text='Seconds before showing popup'),
        ),
        migrations.AddField(
            model_name='popup',
            name='close_delay',
            field=models.IntegerField(default=0, help_text='Seconds before close button appears'),
        ),
        # Frequency
        migrations.AddField(
            model_name='popup',
            name='frequency',
            field=models.CharField(
                choices=[
                    ('every_visit', 'Every Visit'),
                    ('once_session', 'Once Per Session'),
                    ('daily', 'Once Per Day'),
                    ('weekly', 'Once Per Week'),
                    ('monthly', 'Once Per Month'),
                    ('once_ever', 'Once Ever'),
                    ('custom_days', 'Custom Days'),
                ],
                default='once_session',
                max_length=20,
            ),
        ),
        migrations.AddField(
            model_name='popup',
            name='frequency_days',
            field=models.IntegerField(default=0, help_text='Custom days for frequency=custom_days'),
        ),
        # Targeting
        migrations.AddField(
            model_name='popup',
            name='target_audience',
            field=models.CharField(
                choices=[
                    ('all', 'All Visitors'),
                    ('first_time', 'First-Time Visitors'),
                    ('returning', 'Returning Visitors'),
                ],
                default='all',
                max_length=20,
            ),
        ),
        migrations.AddField(
            model_name='popup',
            name='target_pages',
            field=models.TextField(blank=True, default='', help_text='Comma-separated URL paths, supports * wildcard'),
        ),
        # Versioning
        migrations.AddField(
            model_name='popup',
            name='version',
            field=models.IntegerField(default=1),
        ),
    ]
