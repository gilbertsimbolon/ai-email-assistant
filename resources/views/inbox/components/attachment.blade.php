{{-- Satu attachment: thumbnail gambar (lazy-loaded), ikon PDF, atau ikon file generik.
     GHL messages carry a direct attachment URL (no on-demand fetch needed);
     Gmail messages carry an attachment id fetched through our own proxy route. --}}
@php
    $mime = $attachment['mime_type'] ?? '';
    $isGhlAttachment = !isset($attachment['id']) && isset($attachment['url']);
    $downloadUrl = $isGhlAttachment
        ? $attachment['url']
        : route('inbox.messages.attachments.download', [$message->id, $attachment['id']]);
@endphp

@if ($isGhlAttachment)
    <a href="{{ $downloadUrl }}" target="_blank" rel="noopener" class="chat-attachment-file text-decoration-none" title="{{ $attachment['filename'] ?? 'attachment' }}">
        <i class="bx bx-link-external text-secondary fs-3"></i>
        <span class="small d-block text-truncate">{{ $attachment['filename'] ?? 'Lampiran' }}</span>
    </a>
@elseif (str_starts_with($mime, 'image/'))
    <a href="{{ $downloadUrl }}" target="_blank" title="{{ $attachment['filename'] ?? 'attachment' }}">
        <img data-src="{{ route('inbox.messages.attachments.preview', [$message->id, $attachment['id']]) }}"
             class="chat-attachment-thumb lazy-thumb" alt="{{ $attachment['filename'] ?? 'attachment' }}">
    </a>
@elseif ($mime === 'application/pdf')
    <a href="{{ $downloadUrl }}" class="chat-attachment-file text-decoration-none" title="{{ $attachment['filename'] ?? 'attachment' }}">
        <i class="bx bxs-file-pdf text-danger fs-3"></i>
        <span class="small d-block text-truncate">{{ $attachment['filename'] ?? 'file.pdf' }}</span>
    </a>
@else
    <a href="{{ $downloadUrl }}" class="chat-attachment-file text-decoration-none" title="{{ $attachment['filename'] ?? 'attachment' }}">
        <i class="bx bx-file text-secondary fs-3"></i>
        <span class="small d-block text-truncate">{{ $attachment['filename'] ?? 'file' }}</span>
    </a>
@endif
