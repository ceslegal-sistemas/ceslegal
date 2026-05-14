@php $ok = $ok ?? false; $feedback = $feedback ?? ''; @endphp
@if($feedback)
<div style="
    display:flex;
    align-items:flex-start;
    gap:.5rem;
    padding:.6rem .875rem;
    border-radius:.6rem;
    font-size:.8125rem;
    line-height:1.55;
    margin-top:-.25rem;
    {{ $ok
        ? 'background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25);color:#16a34a;'
        : 'background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.28);color:#b45309;' }}
">
    @if($ok)
    <svg style="width:15px;height:15px;flex-shrink:0;margin-top:1px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    @else
    <svg style="width:15px;height:15px;flex-shrink:0;margin-top:1px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
    </svg>
    @endif
    <span>{{ $feedback }}</span>
</div>
@endif
