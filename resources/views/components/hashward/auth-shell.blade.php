@props([
    'step' => null,
    'stepTotal' => 3,
    'title',
    'lead' => null,
])

<main id="main" class="sb-auth-wrap">
    <div class="mb-8 text-center">
        <x-hashward.brand :logo="true" />
    </div>

    <div class="sb-panel">
        @if ($step)
            <p class="sb-step">Step {{ $step }} of {{ $stepTotal }}</p>
        @endif

        <h1 class="mb-2 text-2xl font-semibold tracking-tight">{{ $title }}</h1>

        @if ($lead)
            <p class="sb-lead">{{ $lead }}</p>
        @endif

        {{ $slot }}
    </div>
</main>
