@extends('admin.layout')

@section('page_title', 'Dashboard')

@section('content')
<div x-data="{ 
    loading: false, 
    currentGrade: '{{ request('grade_level', '') }}',
    fetchDashboard(grade) {
        this.loading = true;
        this.currentGrade = grade;
        
        let url = new URL('{{ route('admin.dashboard') }}');
        if(grade) url.searchParams.set('grade_level', grade);

        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            document.getElementById('main-dashboard-content').innerHTML = html;
            
            // CRITICAL: Re-trigger Chart.js initialization
            const event = new Event('DOMContentLoaded');
            document.dispatchEvent(event);
            
            this.loading = false;
        });
    }
}" @refresh-dashboard.window="fetchDashboard(currentGrade)">

    <div class="sticky top-0 z-10 bg-white py-1"> 
        <div class="inline-flex rounded-xl shadow-md border border-gray-100 mb-4 bg-[#F7FBF9]">
            @foreach(['All' => '', 'Grade 11' => 'Grade 11', 'Grade 12' => 'Grade 12'] as $label => $value)
                <button 
                    @click="fetchDashboard('{{ $value }}')"
                    :class="currentGrade === '{{ $value }}' ? 'bg-[#00568d] text-white' : 'text-[#00568d] hover:text-[#00568d]/50'"
                    class="px-6 py-2 rounded-xl font-semibold text-sm transition-all shadow-sm">
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div id="main-dashboard-content" class="relative min-h-[400px]">
        <div x-show="loading" 
            x-transition:enter="transition opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition opacity ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-cloak 
            class="absolute inset-0 bg-white/60 z-50 flex items-center justify-center rounded-2xl backdrop-blur-[1px]">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-[#003918]"></div>
        </div>

        @include('admin.dashboardpage.partials._dashboard_wrapper')
    </div>
</div>
@endsection