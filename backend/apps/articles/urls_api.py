from django.urls import path

from . import views

app_name = 'articles_api'
urlpatterns = [
    path('search/', views.search_api, name='search_api'),
    path('tags/', views.tags_api, name='tags'),
    path('tags/search/', views.tag_search_api, name='tag_search'),
    path('trending/', views.trending_api, name='trending'),
    path('comment/', views.comment_post, name='comment_post'),
    path('follow-topic/', views.follow_topic, name='follow_topic'),
    path('unfollow-topic/', views.unfollow_topic, name='unfollow_topic'),
    path('share-track/', views.share_track, name='share_track'),
    path('engagement/', views.engagement_track, name='engagement_track'),
    path('v1/articles/', views.syndication_list, name='syndication_list'),
    path('v1/articles/<slug:slug>/', views.syndication_detail, name='syndication_detail'),
]
