from django.urls import path

from . import views

urlpatterns = [
    path('login/', views.login_view, name='admin_login'),
    path('logout/', views.logout_view, name='admin_logout'),
    path('forgot-password/', views.forgot_password, name='admin_forgot_password'),
    path('reset-password/<str:token>/', views.reset_password, name='admin_reset_password'),
    path('users/', views.user_list, name='admin_users'),
    path('users/create/', views.user_create, name='admin_user_create'),
    path('users/<uuid:pk>/edit/', views.user_edit, name='admin_user_edit'),
    path('users/<uuid:pk>/delete/', views.user_delete, name='admin_user_delete'),
    path('roles/', views.role_list, name='admin_roles'),
    path('roles/create/', views.role_create, name='admin_role_create'),
    path('roles/<uuid:pk>/edit/', views.role_edit, name='admin_role_edit'),
    path('roles/<uuid:pk>/delete/', views.role_delete, name='admin_role_delete'),
    path('profile/', views.profile, name='admin_profile'),
]
