@props([
    'selectedMonth'=>'',
    'fieldRequired'=>false,
])

    @if(!$fieldRequired)<option value="">--</option> @endif

    @foreach(range(1,\Carbon\Carbon::MONTHS_PER_YEAR) as $monthNumber))

    <option @selected ($selectedMonth == $monthNumber) value="{{$monthNumber}}">

        {{now()->startOfMonth()->month($monthNumber)->translatedFormat('F')}}
    </option>

    @endforeach
