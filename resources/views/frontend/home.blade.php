@extends('layouts.app')

@section('title', 'Sai Consulting | દસ્તાવેજ ડ્રાફ્ટિંગ અને કન્સલ્ટિંગ')
@section('description', 'Sai Consulting દ્વારા વિશ્વાસપાત્ર દસ્તાવેજ ડ્રાફ્ટિંગ, મિલકત દસ્તાવેજ માર્ગદર્શન, ઓનલાઇન વિનંતી અને સુરક્ષિત ટ્રેકિંગ સેવા.')

@section('content')
    @include('frontend.sections.hero')
    @include('frontend.sections.trust-highlights')
    @include('frontend.sections.services')
    @include('frontend.sections.process')
    @include('frontend.sections.why-choose')
    @include('frontend.sections.statistics')
    @include('frontend.sections.tracking-cta')
    @include('frontend.sections.about')
    @include('frontend.sections.office-information')
    @include('frontend.sections.faq')
    @include('frontend.sections.upload-cta')
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const navbar = document.querySelector('.site-navbar');
    const navLinks = document.querySelectorAll('.site-navbar .nav-link[href*="#"]');
    const counters = document.querySelectorAll('.stat-counter');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const updateNavbar = () => navbar?.classList.toggle('is-scrolled', window.scrollY > 18);
    updateNavbar();
    window.addEventListener('scroll', updateNavbar, { passive: true });

    const sections = [...navLinks].map(link => document.querySelector(new URL(link.href).hash)).filter(Boolean);
    if (sections.length) {
        const sectionObserver = new IntersectionObserver(entries => entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            navLinks.forEach(link => link.classList.toggle('active', new URL(link.href).hash === `#${entry.target.id}`));
        }), { rootMargin: '-35% 0px -55%' });
        sections.forEach(section => sectionObserver.observe(section));
    }

    const animateCounter = counter => {
        const target = Number(counter.dataset.count);
        if (reduceMotion || target === 0) { counter.textContent = target.toLocaleString(); return; }
        const start = performance.now();
        const tick = now => {
            const progress = Math.min((now - start) / 1100, 1);
            counter.textContent = Math.floor(target * (1 - Math.pow(1 - progress, 3))).toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    };
    const counterObserver = new IntersectionObserver(entries => entries.forEach(entry => {
        if (entry.isIntersecting && !entry.target.dataset.animated) {
            entry.target.dataset.animated = 'true';
            animateCounter(entry.target);
        }
    }), { threshold: .5 });
    counters.forEach(counter => counterObserver.observe(counter));
});
</script>
@endpush
