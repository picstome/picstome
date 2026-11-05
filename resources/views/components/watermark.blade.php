{{--
    Renders the active team watermark (image or text) over a photo.

    The caller must place this component inside a positioned container. The
    "versions" variant adds the padding and z-index used by the comparison
    modal; the default variant is used by gallery tiles.

    The text is always escaped by Blade and is decorative, so it is hidden from
    assistive technology and never captures pointer events.
--}}

@props([
    'team',
    'position' => null,
    'variant' => 'tile',
    'type' => null,
    'text' => null,
    'imageUrl' => null,
    'font' => null,
    'size' => null,
    'opacity' => null,
])

@php
    $type = $type ?? $team->brand_watermark_type;
    $text = $text ?? $team->brand_watermark_text;
    $imageUrl = $imageUrl ?? $team->brand_watermark_url;
    $font = $font === null ? $team->brand_watermark_font : $font;
    $font = filled($font) ? $font : null;
    $size = $size ?? $team->brand_watermark_size;
    $opacity = $opacity ?? $team->brand_watermark_opacity;
    $position = $position ?? $team->brand_watermark_position ?? 'top';

    $sizeConfig = config('picstome.watermark_sizes.'.$size, config('picstome.watermark_sizes.md'));
    $fontPx = (int) ($sizeConfig['px'] ?? 26);
    $fontPx = $variant === 'versions' ? (int) round($fontPx * 1.35) : $fontPx;
    $fontFamily = $font ? "'{$font}', sans-serif" : 'ui-sans-serif, system-ui, sans-serif';

    $isText = $type === 'text' && filled($text);
    $isImage = $type !== 'text' && filled($imageUrl);
    $isRepeated = $position === 'repeated';
    $wrapperClass = $variant === 'versions'
        ? 'pointer-events-none absolute inset-0 z-10 flex overflow-hidden'
        : 'pointer-events-none absolute flex justify-center';
    $alignmentClass = match ($position) {
        'bottom' => 'inset-x-0 bottom-0',
        'middle' => 'inset-0 items-center',
        default => 'inset-x-0 top-0',
    };
    $paddingClass = match (true) {
        $variant !== 'versions' => '',
        $position === 'top' => 'pt-6',
        $position === 'bottom' => 'pb-6',
        default => '',
    };
    $imageClass = $variant === 'versions'
        ? 'max-h-1/2 max-w-1/2 object-contain'
        : 'max-w-full';
    $textClass = $variant === 'versions'
        ? 'block max-h-1/2 max-w-1/2'
        : 'block max-w-full';
    $repeatedSize = $variant === 'versions' ? '120px' : '75%';
    $repeatedClass = $variant === 'versions' ? 'z-10' : '';
@endphp

@if ($isImage)
    @if ($isRepeated)
        <div
            data-watermark-image
            class="pointer-events-none absolute inset-0 {{ $repeatedClass }}"
            style="background-image: url('{{ $imageUrl }}'); background-repeat: repeat; background-size: {{ $repeatedSize }}; opacity: {{ $opacity }};"
            aria-hidden="true"
        ></div>
    @else
        <div class="{{ $wrapperClass }} {{ $alignmentClass }} {{ $paddingClass }}" aria-hidden="true">
            <img
                data-watermark-image
                src="{{ $imageUrl }}"
                alt=""
                class="{{ $imageClass }}"
                style="opacity: {{ $opacity }};"
                loading="lazy"
            />
        </div>
    @endif
@elseif ($isText)
    @if ($isRepeated)
        @php
            $patternId = 'watermark-'.str()->uuid();
            $patternHeight = max(70, (int) round($fontPx * 3.5));
            $patternWidth = min(900, max(240, (int) round(mb_strlen($text) * $fontPx * 0.65)));
            $patternTextY = (int) round($patternHeight * 0.6);
        @endphp
        <svg
            data-watermark-text
            class="pointer-events-none absolute inset-0 h-full w-full {{ $repeatedClass }}"
            style="opacity: {{ $opacity }};"
            aria-hidden="true"
        >
            <defs>
                <pattern
                    id="{{ $patternId }}"
                    width="{{ $patternWidth }}"
                    height="{{ $patternHeight }}"
                    patternUnits="userSpaceOnUse"
                    patternTransform="rotate(-20)"
                >
                    <text x="10" y="{{ $patternTextY }}" fill="white" font-size="{{ $fontPx }}" font-family="{{ $fontFamily }}" font-weight="600" style="paint-order: stroke; stroke: rgba(0, 0, 0, 0.45); stroke-width: 2px;">{{ $text }}</text>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#{{ $patternId }})" />
        </svg>
    @else
        <div class="{{ $wrapperClass }} {{ $alignmentClass }} {{ $paddingClass }}" aria-hidden="true">
            <span data-watermark-text class="{{ $textClass }} text-center font-semibold text-white select-none" style="opacity: {{ $opacity }}; font-family: {{ $fontFamily }}; font-size: {{ $fontPx }}px; text-shadow: 0 1px 2px rgba(0, 0, 0, 0.8), 0 0 1px rgba(0, 0, 0, 0.9);">{{ $text }}</span>
        </div>
    @endif
@endif
