@extends('admin.layout')

@section('page_title', 'Access Management')

@section('content')
{{-- 1. Initialize the Alpine.js state at the top level --}}
{{-- In your main access management page --}}
<div x-data="{ 
    showRegistration: false, 
    submitted: false,
    showPassword: false,
    first_name: '', last_name: '', middle_name: '', extension_name: '', birthday: '', role: '', password: '',
    closeModal() {
        this.showRegistration = false;
        this.submitted = false;
        this.first_name = ''; this.last_name = ''; this.middle_name = ''; this.extension_name = ''; this.birthday = ''; 
        this.role = ''; this.password = '';
    }
}">
    
    {{-- 2. Wrap the main content in a div that blurs when 'showRegistration' is true --}}
    <div :class="showRegistration ? 'blur-sm pointer-events-none' : ''" 
         class="transition-all duration-100 bg-transparent overflow-hidden">
        
        {{-- The tabs and button partial --}}
        @include('admin.accessmanagement_page.partials.accessmanagement_tabs')

        {{-- The table partial --}}
        <div class="p-0">
            @include('admin.accessmanagement_page.partials.accessmanagement_table')
        </div>
    </div>

    {{-- 3. Include the Registration Modal outside the blurred area --}}
    @include('admin.accessmanagement_page.partials.accessmanagement_registration')
    
</div>
@endsection