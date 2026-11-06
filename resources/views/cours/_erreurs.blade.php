@if($errors->any())
    <div class="erreurs">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif
