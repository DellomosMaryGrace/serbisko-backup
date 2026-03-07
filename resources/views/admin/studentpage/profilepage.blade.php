@extends('admin.layout')

@section('page_title')
    <div class="flex justify-center items-end w-full pb-2 font-['Inter'] tracking-normal">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-baseline space-x-2">
                <li><a href="{{ route('admin.students') }}" class="text-[16px] font-medium text-gray-500 hover:text-[#00923F] transition-colors">Students</a></li>
                <li class="flex text-[16px] font-bold text-[#00923F]">
                    <span class="mx-2 text-gray-400 select-none">></span>
                    <span>{{ $student->first_name }} {{ $student->last_name }} {{ $student->extension_name ? $student->extension_name : '' }}'s Profile</span>
                </li>
            </ol>
        </nav>
    </div>
@endsection

@section('content')
<div class="p-6 font-['Inter'] tracking-normal space-y-4">
    
    <div class="bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-5">
        <h2 class="text-[#005288] text-2xl font-extrabold uppercase tracking-tight">
            {{ $student->first_name }} {{ $student->middle_name ? substr($student->middle_name, 0, 1) . '.' : '' }} {{ $student->last_name }} {{ $student->extension_name ? $student->extension_name : '' }}
        </h2>
        <h3 class="text-gray-500 text-sm font-bold uppercase tracking-tight">{{ $student->lrn }}</h3>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        @php
        // Calculate Age safely
        $age = '—';
        if (!empty($student->birthday)) {
            try {
                $age = \Carbon\Carbon::parse($student->birthday)->age;
            } catch (\Exception $e) {
                $age = 'Invalid Date';
            }
        }
        @endphp
        {{-- To avoid repeation of lines --}}
        @php
            $renderFields = function($fields, $cols = 'md:grid-cols-2') {
                $html = "<div class='grid grid-cols-1 $cols gap-x-12 gap-y-6'>";
                foreach ($fields as $label => $value) {
                    $val = $value ?: '—';
                    $html .= "
                    <div class='relative border-b-2 border-gray-200 pb-1 group hover:border-[#005288] transition-colors duration-200'>
                        <label class='block text-[10px] font-bold text-gray-400 mb-1'>$label</label>
                        <p class='text-[13px] uppercase text-[#003918] min-h-6'>$val</p>
                    </div>";
                }
                $html .= "</div>";
                return $html;
            };
        @endphp

        <div class="lg:col-span-2 bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-7">
            <h2 class="text-[#005288] text-sm font-extrabold mb-4 uppercase">Learner’s Information</h2>
            {!! $renderFields([
                'LRN:' => $student->lrn, 'Birthdate:' => $student->birthday??'—',
                'Last Name:' => $student->last_name??'—', 'Birthplace:' => $student->place_of_birth??'—',
                'First Name:' => $student->first_name??'—', 'Age:' => $age,
                'Middle Name:' => $student->middle_name??'—', 'Mother Tongue:' => $student->mother_tongue??'—',
                'Extension:' => $student->extension_name ?? '—', 'Gender:' => $student->sex??'—',
            ]) !!}
        </div>

        <div class="bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-7">
            <h2 class="text-[#005288] text-sm font-extrabold mb-4 uppercase">Enrolment</h2>
            {!! $renderFields([
                'School Year:' => $details['School Year'] ?? '—',
                'Grade Level:' => $finalGrade,    
                'Track:'       => $finalTrack,   
                'Cluster of Electives:'    => $finalCluster,  
                'Status:'      => $finalStatus    
            ], 'grid-cols-1') !!}
        </div>

        <div class="lg:col-span-3 bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-8">
            <h2 class="text-[#005288] text-sm font-extrabold mb-4 uppercase">Current Address</h2>
            {!! $renderFields([
                'House #:' => $student->curr_house_number??'—', 'Street:' => $student->curr_street??'—',
                'Barangay:' => $student->curr_barangay??'—', 'City:' => $student->curr_city??'—',
                'Province:' => $student->curr_province??'—', 'Zip:' => $student->curr_zip_code??'—'
            ], 'lg:grid-cols-6') !!}

            <div class="flex items-center gap-3 mt-8 mb-4">
                <h2 class="text-[#005288] text-sm font-extrabold uppercase">Permanent Address</h2>
                @if($student->is_perm_same_as_curr)
                    <span class="text-[9px] bg-[#f1f5fd] text-[#005288] px-2 py-0.5 rounded-full border border-[#00923F]/20 font-bold">SAME AS CURRENT</span>
                @endif
            </div>
            {!! $renderFields([
                'House #:' => $student->perm_house_number??'—', 'Street:' => $student->perm_street??'—',
                'Barangay:' => $student->perm_barangay??'—', 'City:' => $student->perm_city??'—',
                'Province:' => $student->perm_province??'—', 'Zip:' => $student->perm_zip_code??'—'
            ], 'lg:grid-cols-6') !!}
        </div>

        <div class="lg:col-span-3 bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-7 space-y-8">
            @foreach(['Father\'s Name' => 'father', 'Mother\'s Maiden Name' => 'mother', 'Guardian\'s Name' => 'guardian'] as $title => $key)
                <div>
                    <h2 class="text-[#005288] text-sm font-extrabold mb-4 uppercase">{{ $title }}</h2>
                    {!! $renderFields([
                        'Last Name:' => $student->{$key.'_last_name'}??'—', 'First Name:' => $student->{$key.'_first_name'}??'—',
                        'Middle Name:' => $student->{$key.'_middle_name'}??'—', 'Contact:' => $student->{$key.'_contact_number'}??'—'
                    ], 'lg:grid-cols-4') !!}
                </div>
            @endforeach
        </div>
    </div>

    @if(!empty($dynamicDetails))
        <div class="lg:col-span-3 bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-7">
            <h2 class="text-[#005288] text-sm font-extrabold mb-4 uppercase">Additional Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-x-12 gap-y-6">
                @foreach($dynamicDetails as $question => $answer)
                    <div class="relative border-b-2 border-gray-200 pb-1 group hover:border-[#005288] transition-colors duration-200">
                        <label class="block text-[10px] font-bold text-gray-400 mb-1">{{ $question }}</label>
                        <p class="text-[13px] uppercase text-[#003918] min-h-6">{{ $answer ?: '—' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @php
        $status = trim($details['Academic Status'] ?? '');
        // Fuzzy check for 'feree' handles both 'Transferee' and 'Tranferee'
        $isSpecialStatus = str_contains(strtolower($status), 'feree') || 
                        str_contains(strtolower($status), 'balik');
    @endphp

    @if($isSpecialStatus)
        <div class="lg:col-span-3 bg-[#F7FBF9]/40 rounded-xl shadow-md border border-gray-100 p-7">
            <div class="flex items-center gap-2 mb-4">
                <h2 class="text-[#005288] text-sm font-extrabold uppercase">Transferee / Balik-Aral Information</h2>
            </div>
            
            {!! $renderFields([
                'Last School Year Completed:' => $details['Last School Year Completed'] ?? '—',
                'Last Grade Level Completed:' => $details['Last Grade Level Completed'] ?? '—',
                'Last School Attended:'       => $details['Last School Attended'] ?? '—',
                'School ID:'                  => $details['School ID'] ?? '—',
            ], 'lg:grid-cols-4') !!}
        </div>
    @endif
</div>
@endsection