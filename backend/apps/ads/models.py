import uuid

from django.db import models


class AdSlot(models.Model):
    DEVICE_CHOICES = [
        ('all', 'All Devices'),
        ('mobile', 'Mobile'),
        ('desktop', 'Desktop'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=255)
    slot_name = models.CharField(max_length=100, unique=True)
    html_content = models.TextField(blank=True, default='')
    is_active = models.BooleanField(default=True)
    device_targeting = models.CharField(max_length=10, choices=DEVICE_CHOICES, default='all')
    impressions = models.IntegerField(default=0)
    clicks = models.IntegerField(default=0)
    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['name']

    def __str__(self):
        return self.name


class Popup(models.Model):
    TYPE_CHOICES = [
        ('banner', 'Banner'),
        ('modal', 'Modal'),
        ('sidebar', 'Sidebar'),
        ('slide_in', 'Slide In'),
        ('full_screen', 'Full Screen'),
    ]
    DEVICE_CHOICES = [
        ('all', 'All Devices'),
        ('mobile', 'Mobile'),
        ('desktop', 'Desktop'),
    ]
    FREQUENCY_CHOICES = [
        ('every_visit', 'Every Visit'),
        ('once_session', 'Once Per Session'),
        ('daily', 'Once Per Day'),
        ('weekly', 'Once Per Week'),
        ('monthly', 'Once Per Month'),
        ('once_ever', 'Once Ever'),
        ('custom_days', 'Custom Days'),
    ]
    STYLE_CHOICES = [
        ('card_modal', 'Card Modal'),
        ('minimal_bar', 'Minimal Bar'),
        ('split_image', 'Split Image'),
        ('fullscreen', 'Fullscreen'),
        ('slide_in', 'Slide In'),
        ('floating', 'Floating'),
    ]
    POSITION_CHOICES = [
        ('center', 'Center'),
        ('top_left', 'Top Left'),
        ('top_right', 'Top Right'),
        ('bottom_left', 'Bottom Left'),
        ('bottom_right', 'Bottom Right'),
        ('bottom_center', 'Bottom Center'),
    ]
    TRIGGER_CHOICES = [
        ('page_load', 'Page Load'),
        ('time_delay', 'Time Delay'),
        ('scroll_percent', 'Scroll Percentage'),
        ('exit_intent', 'Exit Intent'),
        ('page_views', 'Page View Count'),
    ]
    AUDIENCE_CHOICES = [
        ('all', 'All Visitors'),
        ('first_time', 'First-Time Visitors'),
        ('returning', 'Returning Visitors'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    name = models.CharField(max_length=255)
    type = models.CharField(max_length=20, choices=TYPE_CHOICES, default='modal')
    content = models.TextField(blank=True, default='')
    is_active = models.BooleanField(default=True)

    # Display
    title = models.CharField(max_length=255, blank=True, default='')
    body = models.TextField(blank=True, default='')
    image_url = models.URLField(max_length=500, blank=True, default='')
    banner_style = models.CharField(max_length=20, choices=STYLE_CHOICES, default='card_modal')
    position = models.CharField(max_length=20, choices=POSITION_CHOICES, default='center')

    # Styling
    bg_color = models.CharField(max_length=30, blank=True, default='#ffffff')
    text_color = models.CharField(max_length=30, blank=True, default='#1a1a1a')
    overlay_opacity = models.CharField(max_length=10, blank=True, default='0.5')

    # Buttons
    button_text = models.CharField(max_length=100, blank=True, default='')
    button_url = models.URLField(max_length=500, blank=True, default='')
    btn_bg_color = models.CharField(max_length=30, blank=True, default='#cc0000')
    btn_text_color = models.CharField(max_length=30, blank=True, default='#ffffff')
    secondary_btn_text = models.CharField(max_length=100, blank=True, default='')
    has_email_field = models.BooleanField(default=False)

    # Trigger & timing
    trigger_type = models.CharField(max_length=50, choices=TRIGGER_CHOICES, default='page_load')
    trigger_value = models.CharField(max_length=100, blank=True, default='')
    show_delay = models.IntegerField(default=0, help_text='Seconds before showing popup')
    close_delay = models.IntegerField(default=0, help_text='Seconds before close button appears')

    # Frequency
    frequency = models.CharField(max_length=20, choices=FREQUENCY_CHOICES, default='once_session')
    frequency_days = models.IntegerField(default=0, help_text='Custom days for frequency=custom_days')

    # Targeting
    device_targeting = models.CharField(max_length=10, choices=DEVICE_CHOICES, default='all')
    target_audience = models.CharField(max_length=20, choices=AUDIENCE_CHOICES, default='all')
    target_pages = models.TextField(blank=True, default='', help_text='Comma-separated URL paths, supports * wildcard')

    # Versioning (for dismissal tracking)
    version = models.IntegerField(default=1)

    # Stats
    impressions = models.IntegerField(default=0)
    clicks = models.IntegerField(default=0)

    # A/B testing
    variant = models.CharField(max_length=50, null=True, blank=True, help_text='A/B test variant identifier')

    created_at = models.DateTimeField(auto_now_add=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        ordering = ['name']

    def __str__(self):
        return self.name
