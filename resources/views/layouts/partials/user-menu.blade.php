<div class="relative" x-data="{ userMenu: false }" @keydown.escape.window="userMenu = false" @click.outside="userMenu = false">
    <button type="button" class="user-menu-trigger" @click="userMenu = !userMenu" :aria-expanded="userMenu.toString()">
        <span class="hidden min-w-0 text-left sm:block">
            <span class="block max-w-[160px] truncate text-[12px] font-semibold uppercase tracking-wide text-heading">{{ auth()->user()->name }}</span>
            <span class="block max-w-[160px] truncate text-[11px] text-muted">{{ auth()->user()->email }}</span>
        </span>
        @if (auth()->user()->avatarUrl())
            <img src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" class="user-menu-avatar">
        @else
            <span class="user-menu-avatar user-menu-avatar-fallback">{{ auth()->user()->initials() }}</span>
        @endif
    </button>
    <div class="user-menu-dropdown" x-show="userMenu" x-cloak x-transition.opacity.duration.120ms>
        <a href="{{ route('profile.edit') }}" class="user-menu-item" @click="userMenu = false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
            <span>Profil Saya</span>
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="user-menu-item user-menu-item-danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12H4m11 0l-3-3m3 3l-3 3M10 5h7a2 2 0 012 2v10a2 2 0 01-2 2h-7"/></svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</div>
