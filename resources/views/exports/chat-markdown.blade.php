# {{ $document_version['title'] }} — Chat export

- Chat ID: {{ $chat['id'] }}
- Document version: {{ $document_version['version'] }}
- Status: {{ $chat['status'] }}
- Locale: {{ $chat['locale'] }}

## Conversation
@foreach ($messages as $message)

### {{ ucfirst($message['role']) }}

{{ $message['content'] }}
@endforeach

## Insights

```json
{!! json_encode($insights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) !!}
```
