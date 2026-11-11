@props(['items' => []])

@if(!empty($items))
<nav class="mb-5 flex items-center gap-2 text-xs text-navy-300">
    @foreach($items as $index => $item)
        @if($index > 0)
            <span class="text-navy-300">/</span>
        @endif
        
        @if(isset($item['url']) && $item['url'])
            <a href="{{ $item['url'] }}" class="hover:text-navy-700">
                {{ $item['label'] }}
            </a>
        @else
            <span class="text-navy-500">{{ $item['label'] }}</span>
        @endif
    @endforeach
</nav>
@endif
