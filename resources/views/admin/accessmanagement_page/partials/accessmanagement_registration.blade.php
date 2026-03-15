{{-- SYSTEM MESSAGES --}}
<div 
    x-data="{ 
        show: false, 
        message: '', 
        type: 'success',
        init() {
            @if(session('success'))
                this.showToast('{{ session('success') }}', 'success');
            @elseif(session('info'))
                this.showToast('{{ session('info') }}', 'info');
            @elseif($errors->any())
                this.showToast('{!! implode('<br>', $errors->all()) !!}', 'error');
            @endif
        },
        showToast(msg, type) {
            this.message = msg;
            this.type = type;
            this.show = true;
            setTimeout(() => { this.show = false }, 5000);
        }
    }"
    x-show="show"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-[-20px] opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    x-cloak
    class="fixed top-6 right-6 z-[9999] max-w-sm w-full shadow-2xl rounded-xl overflow-hidden border pointer-events-auto"
    :class="{
        'bg-[#00923F] border-[#003918]': type === 'success',
        'bg-[#3b82f6] border-[#005288]': type === 'info',
        'bg-red-600 border-red-800': type === 'error'
    }"
>
    <div class="p-4 flex items-center gap-4">
        {{-- Icon --}}
        <div class="flex-shrink-0">
            <template x-if="type === 'success'">
                <div class="bg-white/20 rounded-full p-1">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </div>
            </template>
            <template x-if="type === 'info'">
                <div class="bg-white/20 rounded-full p-1">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path d="M12 16v-4m0-4h.01M12 2a10 10 0 100 20 10 10 0 000-20z"/></svg>
                </div>
            </template>
            <template x-if="type === 'error'">
                <div class="bg-white/20 rounded-full p-1">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </div>
            </template>
        </div>

        {{-- Message --}}
        <div class="flex-1">
            <div class="text-white text-[14px] font-bold leading-tight tracking-wide" x-html="message"></div>
        </div>

        {{-- Close --}}
        <button @click="show = false" class="text-white/50 hover:text-white transition-colors duration-200">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    
    {{-- Progress Bar --}}
    <div class="h-1 bg-black/10 w-full overflow-hidden">
        <div 
            x-show="show"
            x-transition:enter="transition-all ease-linear duration-[5000ms]"
            x-transition:enter-start="w-full"
            x-transition:enter-end="w-0"
            class="h-full bg-white/30"
        ></div>
    </div>
</div>


{{-- REGISTRATION FORM --}}
<div x-show="showRegistration" 
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak>
    
    {{-- Background Overlay --}}
    <div @click="closeModal()" class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>

    {{-- Modal Card --}}
    <div x-show="showRegistration"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-90 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4"
         class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl overflow-hidden p-10 transform transition-all">
        
        {{-- Header with Integrated X Icon --}}
        <div class="flex justify-between items-start mb-6">
            <div>
                <h1 class="text-[#003918] text-2xl font-black tracking-tight uppercase">ADD NEW USER</h1>
                <p class="text-gray-500 text-sm font-medium tracking-wide">Create a new account and assign system access.</p>
            </div>

            <button @click="closeModal()" class="text-gray-400 hover:text-gray-600 transition-colors pt-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="opacity-70">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        {{-- Form Content --}}
        <form action="{{ route('admin.accessmanagement.store') }}" method="POST"
            @submit.prevent="
                submitted = true;
                if(first_name && last_name && birthday && role && password){
                    $el.submit();
                }
            ">
            @csrf
            
            {{-- NAME SECTION --}}
            <div class="grid grid-cols-1 md:grid-cols-4 items-start gap-x-6">
                <label class="block text-[13px] font-bold text-[#003918] uppercase tracking-widest pt-3 md:text-right">NAME</label>
                <div class="md:col-span-3 space-y-4">
                    {{-- First Name --}}
                    <div>
                        <input type="text" x-model="first_name" name="first_name" 
                            class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 focus:ring-2 focus:ring-[#00923F] placeholder-gray-400" 
                            placeholder="First Name">
                        <span x-show="submitted && !first_name" x-transition class="text-[10px] text-red-600 font-medium italic mt-1 block">This field is required *</span>
                    </div>

                    {{-- Middle Name --}}
                    <div>
                        <input type="text" x-model="middle_name" name="middle_name" 
                            class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 focus:ring-2 focus:ring-[#00923F] placeholder-gray-400" 
                            placeholder="Middle Name">
                    </div>

                    {{-- Last Name --}}
                    <div>
                        <input type="text" x-model="last_name" name="last_name" 
                            class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 focus:ring-2 focus:ring-[#00923F] placeholder-gray-400" 
                            placeholder="Last Name">
                        <span x-show="submitted && !last_name" x-transition class="text-[10px] text-red-600 font-medium italic mt-1 block">This field is required *</span>
                    </div>
                    
                    {{-- Extension Name --}}
                    <div>
                        <input type="text" x-model="extension_name" name="extension_name" 
                            class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 focus:ring-2 focus:ring-[#00923F] placeholder-gray-400" 
                            placeholder="Extension Name">
                    </div>
                </div>
            </div>

            {{-- BIRTHDAY --}}
            <div class="grid grid-cols-1 md:grid-cols-4 items-center gap-x-6 pt-2">
                <label class="block text-[13px] font-bold text-[#003918] uppercase tracking-widest md:text-right">BIRTHDAY</label>
                <div class="md:col-span-3">
                    <input type="date" x-model="birthday" name="birthday" 
                        class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 focus:ring-2 focus:ring-[#00923F] text-gray-400 uppercase">
                    <span x-show="submitted && !birthday" x-transition class="text-[10px] text-red-600 font-medium italic mt-1 block">This field is required *</span>
                </div>
            </div>

            {{-- ROLE --}}
            <div class="grid grid-cols-1 md:grid-cols-4 items-center gap-x-6 pt-2">
                <label class="block text-[13px] font-bold text-[#003918] uppercase tracking-widest md:text-right">ROLE</label>
                <div class="md:col-span-3 relative">
                    <select x-model="role" name="role" 
                        class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 pr-10 focus:ring-2 focus:ring-[#00923F] text-gray-500 appearance-none cursor-pointer">
                        <option value="" selected disabled>Select Role</option>
                        <option value="admin">Administrator</option>
                        <option value="facilitator">Facilitator</option>
                    </select>

                    {{-- Custom Arrow --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-400">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </div>

                    <span x-show="submitted && !role" x-transition class="text-[10px] text-red-600 font-medium italic mt-1 block">This field is required *</span>
                </div>
            </div>

            {{-- PASSWORD --}}
            <div class="grid grid-cols-1 md:grid-cols-4 items-center gap-x-6 pt-2">
                <label class="block text-[13px] font-bold text-[#003918] uppercase tracking-widest md:text-right">PASSWORD</label>
                <div class="md:col-span-3 relative">
                    {{-- Input type changes between 'password' and 'text' --}}
                    <input :type="showPassword ? 'text' : 'password'" 
                        x-model="password" name="password" 
                        class="w-full bg-[#E8EEF4]/50 border-gray-100 rounded-lg text-sm px-4 py-3 pr-12 focus:ring-2 focus:ring-[#00923F] placeholder-gray-400" 
                        placeholder="Enter password">

                    {{-- Clickable Eye Icon Container --}}
                    <button type="button" 
                            @click="showPassword = !showPassword" 
                            class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-400 hover:text-[#00923F] transition-colors pt-0">
                        
                        {{-- Eye Icon (Visible when showPassword is false) --}}
                        <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>

                        {{-- Eye-off Icon (Visible when showPassword is true) --}}
                        <svg x-show="showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                            <line x1="1" y1="1" x2="23" y2="23"></line>
                        </svg>
                    </button>

                    <span x-show="submitted && !password" x-transition class="text-[10px] text-red-600 font-medium italic mt-1 block">This field is required *</span>
                </div>
            </div>
            {{-- Footer Buttons --}}
            <div class="flex justify-center items-center gap-5 pt-2">
                <button type="button" @click="closeModal()" 
                    class="px-8 py-3 bg-[#E8EEF4] text-gray-600 font-bold text-sm rounded-xl uppercase tracking-widest hover:bg-gray-200 transition-colors">CANCEL</button>
                <button type="submit" 
                    class="px-8 py-3 bg-[#00923F] text-white font-bold text-sm rounded-xl uppercase tracking-widest hover:bg-[#003918] shadow-md transition-all scale-100 hover:scale-105">ADD</button>
            </div>
        </form>
    </div>
</div>





