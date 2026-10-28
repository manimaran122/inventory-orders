{{-- Session feedback after a redirect, plus any validation summary. --}}

@if(session('success'))
    <div class="alert-success mb-6" role="status">
        <svg class="mt-0.5 size-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd"/>
        </svg>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if(session('error'))
    <div class="alert-error mb-6" role="alert">
        <svg class="mt-0.5 size-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div>{{ session('error') }}</div>
    </div>
@endif

@if($errors->any() && ! $errors->has('__form_only'))
    <div class="alert-error mb-6" role="alert">
        <svg class="mt-0.5 size-5 shrink-0" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div>
            <p class="font-medium">
                {{ $errors->count() === 1 ? 'There is a problem with your submission' : 'There are ' . $errors->count() . ' problems with your submission' }}
            </p>
            <ul class="mt-1.5 list-inside list-disc space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
