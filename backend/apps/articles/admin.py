from django.contrib import admin
from django.utils.html import format_html

from .models import (
    Article, ArticleEntity, ArticleRevision, ArticleTag,
    Category, Comment, StoryCluster, Tag, TopicFollow,
)


@admin.register(Category)
class CategoryAdmin(admin.ModelAdmin):
    list_display = ('name', 'slug', 'show_in_nav', 'show_in_sidebar', 'sort_order')
    list_editable = ('show_in_nav', 'show_in_sidebar', 'sort_order')
    search_fields = ('name',)
    prepopulated_fields = {'slug': ('name',)}


@admin.register(Tag)
class TagAdmin(admin.ModelAdmin):
    list_display = ('name', 'slug')
    search_fields = ('name',)
    prepopulated_fields = {'slug': ('name',)}


class ArticleTagInline(admin.TabularInline):
    model = ArticleTag
    extra = 1
    raw_id_fields = ('tag',)


@admin.register(ArticleTag)
class ArticleTagAdmin(admin.ModelAdmin):
    list_display = ('article', 'tag')
    list_filter = ('tag',)
    search_fields = ('article__title', 'tag__name')
    raw_id_fields = ('article', 'tag')
    list_per_page = 100


@admin.register(Article)
class ArticleAdmin(admin.ModelAdmin):
    list_display = (
        'external_id_short', 'title_short', 'category', 'author_display',
        'status', 'sentiment_display', 'importance_display', 'follow_up_display',
        'controversy_display', 'rewrite_status', 'analyzed_at_display',
        'est_cost_display',
    )
    list_filter = (
        'status', 'rewrite_status', 'sentiment', 'is_crawled',
        'category', 'source_name', 'created_at',
    )
    search_fields = ('title', 'source_name', 'source_url', 'display_author')
    list_per_page = 100
    date_hierarchy = 'created_at'
    readonly_fields = (
        'id', 'source_hash', 'embedding', 'ai_summary', 'sentiment',
        'sentiment_score', 'quality_score', 'created_at', 'updated_at',
    )
    raw_id_fields = ('author', 'category')
    inlines = [ArticleTagInline]
    actions = ['mark_published', 'mark_pending', 'queue_rewrite']

    fieldsets = (
        (None, {'fields': ('title', 'slug', 'excerpt', 'content', 'featured_image')}),
        ('Publishing', {'fields': ('status', 'published_at', 'is_breaking_manual', 'category', 'author')}),
        ('Source', {'fields': ('is_crawled', 'source_url', 'source_name', 'source_hash', 'display_author')}),
        ('AI Enrichment', {
            'fields': ('ai_summary', 'sentiment', 'sentiment_score', 'quality_score'),
            'description': 'AI-generated analysis from the enrichment pipeline.',
        }),
        ('Rewriting', {'fields': ('rewrite_status', 'rewritten_title', 'rewritten_content', 'rewritten_excerpt')}),
        ('Clustering', {'fields': ('story_cluster_id',)}),
        ('Meta', {'fields': ('id', 'created_at', 'updated_at', 'deleted_at')}),
    )

    def external_id_short(self, obj):
        return str(obj.id)[:8]
    external_id_short.short_description = 'ID'

    def title_short(self, obj):
        return obj.title[:55] + '...' if len(obj.title) > 55 else obj.title
    title_short.short_description = 'Title'

    def author_display(self, obj):
        if obj.display_author:
            name = obj.display_author
            if obj.source_name:
                name += f' ({obj.source_name})'
            return name
        if obj.author:
            return str(obj.author)
        return '-'
    author_display.short_description = 'Author'

    def sentiment_display(self, obj):
        colors = {'positive': '#28a745', 'negative': '#dc3545', 'neutral': '#6c757d', 'mixed': '#ffc107'}
        s = obj.sentiment or 'neutral'
        color = colors.get(s, '#6c757d')
        return format_html(
            '<span style="color:{}; font-weight:600">{}</span>',
            color, s.title(),
        )
    sentiment_display.short_description = 'Sentiment'
    sentiment_display.admin_order_field = 'sentiment'

    def importance_display(self, obj):
        score = obj.quality_score
        if score is None:
            return '-'
        s = int(round(score / 10))
        if s >= 7:
            color = '#28a745'
        elif s >= 4:
            color = '#ffc107'
        else:
            color = '#dc3545'
        return format_html('<strong style="color:{}">{}</strong>', color, s)
    importance_display.short_description = 'Score'
    importance_display.admin_order_field = 'quality_score'

    def follow_up_display(self, obj):
        if obj.quality_score is not None and obj.quality_score >= 70:
            return format_html('<span style="color:#28a745;font-size:16px" title="Follow-up worthy">&#10004;</span>')
        return format_html('<span style="color:#dc3545;font-size:16px" title="Not flagged">&#10008;</span>')
    follow_up_display.short_description = 'Follow Up'

    def controversy_display(self, obj):
        if obj.sentiment in ('negative', 'mixed'):
            return format_html('<span style="color:#dc3545;font-size:16px" title="Controversial">&#10004;</span>')
        return format_html('<span style="color:#28a745;font-size:16px" title="Not controversial">&#10008;</span>')
    controversy_display.short_description = 'Controversy'

    def analyzed_at_display(self, obj):
        return obj.updated_at
    analyzed_at_display.short_description = 'Analyzed'
    analyzed_at_display.admin_order_field = 'updated_at'

    def est_cost_display(self, obj):
        # Estimate based on content length: ~$0.0005 per 1K tokens (GPT-4o-mini)
        content_len = len(obj.content or '')
        tokens = content_len / 4  # rough char-to-token ratio
        cost = (tokens / 1000) * 0.00015 * 2  # input + output
        if obj.rewrite_status == 'completed':
            rewrite_tokens = len(obj.rewritten_content or '') / 4
            cost += (rewrite_tokens / 1000) * 0.0006  # output tokens cost more
        return f'${cost:.5f}'
    est_cost_display.short_description = 'Est. Cost'

    @admin.action(description='Mark selected as Published')
    def mark_published(self, request, queryset):
        queryset.update(status='published')

    @admin.action(description='Mark selected as Pending Review')
    def mark_pending(self, request, queryset):
        queryset.update(status='pending_review')

    @admin.action(description='Queue selected for Rewriting')
    def queue_rewrite(self, request, queryset):
        queryset.update(rewrite_status='queued')


@admin.register(Comment)
class CommentAdmin(admin.ModelAdmin):
    list_display = ('article', 'name', 'email', 'status', 'created_at')
    list_filter = ('status', 'created_at')
    search_fields = ('name', 'content', 'article__title')
    readonly_fields = ('created_at',)


@admin.register(ArticleRevision)
class ArticleRevisionAdmin(admin.ModelAdmin):
    list_display = ('article', 'created_by', 'created_at')
    readonly_fields = ('article', 'created_by', 'title', 'content', 'created_at')


@admin.register(StoryCluster)
class StoryClusterAdmin(admin.ModelAdmin):
    list_display = ('title', 'canonical_article', 'created_at')
    search_fields = ('title',)
    readonly_fields = ('created_at',)


@admin.register(ArticleEntity)
class ArticleEntityAdmin(admin.ModelAdmin):
    list_display = ('article', 'entity_type', 'entity_text', 'salience')
    list_filter = ('entity_type',)
    search_fields = ('entity_text', 'article__title')


@admin.register(TopicFollow)
class TopicFollowAdmin(admin.ModelAdmin):
    list_display = ('email', 'follow_type', 'follow_id', 'created_at')
    list_filter = ('follow_type',)
    readonly_fields = ('created_at',)
