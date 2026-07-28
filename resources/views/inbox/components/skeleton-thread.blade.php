{{-- Skeleton shimmer ditampilkan sesaat saat AJAX memuat thread percakapan lain (lihat inbox-navigation.js) --}}
<div class="email-thread d-flex flex-column h-100 skeleton-thread">
    <div class="border-bottom bg-white flex-shrink-0 px-4 py-3">
        <div class="skeleton-line skeleton-line-title mb-2"></div>
        <div class="skeleton-line skeleton-line-sm"></div>
    </div>
    <div class="chat-history flex-grow-1 overflow-hidden p-4">
        <div class="d-flex mb-3 justify-content-start">
            <div class="skeleton-bubble" style="width: 55%;"></div>
        </div>
        <div class="d-flex mb-3 justify-content-end">
            <div class="skeleton-bubble" style="width: 45%;"></div>
        </div>
        <div class="d-flex mb-3 justify-content-start">
            <div class="skeleton-bubble" style="width: 65%;"></div>
        </div>
    </div>
</div>
