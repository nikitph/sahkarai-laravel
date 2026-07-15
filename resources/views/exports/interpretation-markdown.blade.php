# {{ $document->title }}

{{ $interpretation['summary'] }}

## Key takeaways
@foreach ($interpretation['takeaways'] as $takeaway)
- {{ $takeaway }}
@endforeach

@if(count($interpretation['glossary']))
## Glossary
@foreach ($interpretation['glossary'] as $item)
- **{{ $item['term'] }}:** {{ $item['definition'] }}
@endforeach
@endif

---
Generated with {{ $meta->model_id }} · Prompt {{ $meta->prompt_version }}. Educational information, not legal advice.
