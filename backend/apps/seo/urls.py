from django.urls import path

from . import views

urlpatterns = [
    path('seo/', views.audit_list, name='admin_seo'),
    path('seo/detail/<uuid:pk>/', views.audit_detail, name='admin_seo_detail'),
    path('seo/run-audit/', views.run_audit, name='admin_seo_run'),
    path('seo/report-pdf/<uuid:pk>/', views.report_pdf, name='admin_seo_report'),
]
