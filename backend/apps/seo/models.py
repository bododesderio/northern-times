import uuid

from django.db import models


class SeoAudit(models.Model):
    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    score = models.IntegerField(default=0)
    critical_count = models.IntegerField(default=0)
    warning_count = models.IntegerField(default=0)
    info_count = models.IntegerField(default=0)
    pages_scanned = models.IntegerField(default=0)
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'Audit #{self.pk} — Score: {self.score}'


class SeoIssue(models.Model):
    SEVERITY_CHOICES = [
        ('critical', 'Critical'),
        ('warning', 'Warning'),
        ('info', 'Info'),
    ]

    id = models.UUIDField(primary_key=True, default=uuid.uuid4, editable=False)
    audit = models.ForeignKey(
        SeoAudit,
        on_delete=models.CASCADE,
        related_name='issues',
    )
    url = models.URLField(max_length=500)
    issue_type = models.CharField(max_length=100)
    severity = models.CharField(max_length=10, choices=SEVERITY_CHOICES, default='info')
    message = models.TextField()
    created_at = models.DateTimeField(auto_now_add=True)

    class Meta:
        ordering = ['-created_at']

    def __str__(self):
        return f'[{self.severity}] {self.issue_type} — {self.url}'
