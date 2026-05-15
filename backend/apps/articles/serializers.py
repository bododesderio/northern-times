from rest_framework import serializers

from .models import Article, Category, Comment, Tag


class CategorySerializer(serializers.ModelSerializer):
    class Meta:
        model = Category
        fields = ['id', 'name', 'slug', 'color', 'icon']


class TagSerializer(serializers.ModelSerializer):
    class Meta:
        model = Tag
        fields = ['id', 'name', 'slug', 'type']


class CommentSerializer(serializers.ModelSerializer):
    class Meta:
        model = Comment
        fields = ['id', 'name', 'content', 'created_at', 'parent']
        read_only_fields = ['id', 'created_at']


class ArticleListSerializer(serializers.ModelSerializer):
    category = CategorySerializer(read_only=True)
    reading_time = serializers.IntegerField(read_only=True)

    class Meta:
        model = Article
        fields = [
            'id', 'title', 'slug', 'excerpt', 'featured_image',
            'category', 'published_at', 'reading_time', 'display_author',
            'source_name', 'quality_score',
        ]


class ArticleDetailSerializer(serializers.ModelSerializer):
    category = CategorySerializer(read_only=True)
    tags = TagSerializer(many=True, read_only=True)
    comments = serializers.SerializerMethodField()
    reading_time = serializers.IntegerField(read_only=True)
    author_name = serializers.SerializerMethodField()

    class Meta:
        model = Article
        fields = [
            'id', 'title', 'slug', 'excerpt', 'content', 'featured_image',
            'category', 'tags', 'comments', 'published_at', 'reading_time',
            'display_author', 'author_name', 'source_url', 'source_name',
            'ai_summary', 'sentiment', 'quality_score',
        ]

    def get_comments(self, obj):
        approved = obj.comments.filter(status='approved').order_by('created_at')
        return CommentSerializer(approved, many=True).data

    def get_author_name(self, obj):
        if obj.display_author:
            return obj.display_author
        if obj.author:
            return obj.author.display_name or obj.author.username
        return 'Staff'
