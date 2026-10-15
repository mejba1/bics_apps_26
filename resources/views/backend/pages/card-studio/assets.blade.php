{{-- Card Design Studio: fonts for the designs, QR library and the shared card renderer --}}
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&family=Inter:wght@300..800&family=Montserrat:ital,wght@0,300..800;1,300..800&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,700&family=Roboto:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@300..700&family=Playfair+Display:ital,wght@0,400..800;1,400..800&family=Lora:ital,wght@0,400..700;1,400..700&family=Merriweather:ital,wght@0,300;0,400;0,700;1,400&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script src="{{ asset('assets/backend/card-studio/card-renderer.js') }}?v={{ @filemtime(base_path('assets/backend/card-studio/card-renderer.js')) }}"></script>
<script>window.CARD_BASE = @json(url('/') . '/');</script>
<script src="{{ asset('assets/backend/card-studio/card-export.js') }}?v={{ @filemtime(base_path('assets/backend/card-studio/card-export.js')) }}"></script>
