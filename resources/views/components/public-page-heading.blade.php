@props(['titleGu', 'titleEn', 'intro' => null])
<section class="information-page-hero" aria-labelledby="page-title">
    <div class="container py-5">
        <nav aria-label="Breadcrumb"><ol class="breadcrumb service-breadcrumb"><li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li><li class="breadcrumb-item active" aria-current="page">{{ $titleEn }}</li></ol></nav>
        <h1 id="page-title">{{ $titleGu }}</h1>
        <p class="information-page-english">{{ $titleEn }}</p>
        @if($intro)<p class="information-page-intro mb-0">{{ $intro }}</p>@endif
    </div>
</section>
