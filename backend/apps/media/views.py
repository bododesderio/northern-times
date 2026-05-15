import hashlib
import os
import uuid

from django.conf import settings
from django.contrib.auth.decorators import login_required
from django.core.paginator import Paginator
from django.http import JsonResponse
from django.shortcuts import get_object_or_404, redirect, render
from django.views.decorators.http import require_POST

from .models import MediaItem


@login_required
def media_index(request):
    """Media library: paginated grid of all media items."""
    qs = MediaItem.objects.order_by('-created_at')

    mime_filter = request.GET.get('type')
    if mime_filter:
        qs = qs.filter(mime_type__startswith=mime_filter)

    search = request.GET.get('q', '').strip()
    if search:
        qs = qs.filter(original_filename__icontains=search)

    paginator = Paginator(qs, 24)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/media/index.html', {
        'items': page,
        'active_nav': 'media',
        'page_title': 'Media Library',
    })


@login_required
def media_picker(request):
    """Media picker modal for article editor (rendered without full layout)."""
    qs = MediaItem.objects.filter(mime_type__startswith='image').order_by('-created_at')
    paginator = Paginator(qs, 20)
    page = paginator.get_page(request.GET.get('page'))
    return render(request, 'admin/media/picker.html', {
        'items': page,
        'active_nav': 'media',
        'page_title': 'Media Picker',
    })


@login_required
@require_POST
def media_upload(request):
    """Handle file upload and save MediaItem."""
    uploaded = request.FILES.get('file')
    if not uploaded:
        return JsonResponse({'error': 'No file provided'}, status=400)

    # Read file content for hashing
    file_content = uploaded.read()
    file_hash = hashlib.sha256(file_content).hexdigest()
    uploaded.seek(0)

    # Check for duplicate by hash
    existing = MediaItem.objects.filter(hash=file_hash).first()
    if existing:
        return JsonResponse({
            'id': str(existing.pk),
            'url': existing.path,
            'filename': existing.original_filename,
            'duplicate': True,
        })

    # Save file to disk
    ext = os.path.splitext(uploaded.name)[1].lower()
    new_filename = f'{uuid.uuid4().hex}{ext}'
    upload_dir = os.path.join(settings.MEDIA_ROOT, 'uploads')
    os.makedirs(upload_dir, exist_ok=True)
    file_path = os.path.join(upload_dir, new_filename)

    with open(file_path, 'wb') as f:
        for chunk in uploaded.chunks():
            f.write(chunk)

    # Detect dimensions for images
    width = height = None
    if uploaded.content_type and uploaded.content_type.startswith('image'):
        try:
            from PIL import Image
            img = Image.open(file_path)
            width, height = img.size
        except Exception:
            pass

    item = MediaItem.objects.create(
        filename=new_filename,
        original_filename=uploaded.name,
        path=f'/media/uploads/{new_filename}',
        mime_type=uploaded.content_type or 'application/octet-stream',
        size=len(file_content),
        width=width,
        height=height,
        hash=file_hash,
        alt_text=request.POST.get('alt_text', ''),
        folder=request.POST.get('folder', ''),
        uploaded_by=request.user,
    )

    return JsonResponse({
        'id': str(item.pk),
        'url': item.path,
        'filename': item.original_filename,
    })


@login_required
@require_POST
def media_delete(request, pk):
    """Delete a single media item and its file."""
    item = get_object_or_404(MediaItem, pk=pk)
    # Remove file from disk
    full_path = os.path.join(settings.MEDIA_ROOT, item.path.lstrip('/media/'))
    if os.path.exists(full_path):
        os.remove(full_path)
    item.delete()

    if request.headers.get('X-Requested-With') == 'XMLHttpRequest':
        return JsonResponse({'status': 'ok'})
    return redirect('admin_media')


@login_required
@require_POST
def media_bulk_delete(request):
    """Bulk delete selected media items."""
    ids = request.POST.getlist('ids')
    if ids:
        items = MediaItem.objects.filter(pk__in=ids)
        for item in items:
            full_path = os.path.join(settings.MEDIA_ROOT, item.path.lstrip('/media/'))
            if os.path.exists(full_path):
                os.remove(full_path)
        items.delete()
    return redirect('admin_media')
