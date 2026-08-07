<section class="statistics-section" aria-label="Sai Consulting statistics">
    <div class="container"><div class="statistics-panel"><div class="row g-4 text-center">
        @foreach($homepage['statistics'] as $stat)
            <div class="col-6 col-lg-3"><div class="stat-item"><strong><span class="stat-counter" data-count="{{ $stat['value'] }}">0</span>{{ $stat['suffix'] }}</strong><span>{{ $stat['label_gu'] }}</span></div></div>
        @endforeach
    </div></div></div>
</section>
