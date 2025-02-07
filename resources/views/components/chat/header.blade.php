@use("Namu\WireChat\Facades\WireChat")

@props([
    'receiver' => $receiver,
    'conversation' => $conversation,
])

@php
    $group = $conversation->group;
@endphp

<header
    class="w-full  sticky inset-x-0 flex pb-[5px] pt-[7px] top-0 z-10 bg-gray-50 dark:bg-gray-800 dark:border-gray-800/80  border-b">

    <div class="flex w-full items-center px-2 py-2 lg:px-4 gap-2 md:gap-5">
        {{-- Return --}}
        <a href="{{route(WireChat::indexRouteName())}}" class="shrink-0 lg:hidden dark:text-white" id="chatReturn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
            </svg>
        </a>

        {{-- Receiver wirechat::Avatar --}}
        <section class="flex w-full">
            <div class="shrink-0 col-span-11 truncate overflow-h-hidden relative">
                <div class="flex items-center gap-2 cursor-pointer ">
                    <x-wirechat::avatar disappearing="{{$conversation->hasDisappearingTurnedOn()}}" group="{{ $conversation->isGroup() }}"
                        src="{{ $group ? $group?->cover_url : $receiver?->cover_url ?? null }}"
                        class="h-8 w-8 lg:w-10 lg:h-10 " />
                    <h6 class="font-bold text-base text-gray-800 dark:text-white w-full truncate">
                        {{ $group ? $group?->name : $receiver?->display_name }} @if ($conversation->isSelfConversation())
                            ({{__('wirechat.You')}})
                        @endif
                    </h6>
                </div>

           
            </div>

            {{-- Header Actions --}}
            <div class="flex gap-2 items-center ml-auto col-span-1">
                <x-wirechat::dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex px-0 text-gray-700 dark:text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.9" stroke="currentColor" class="size-6 w-7 h-7">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z" />
                            </svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">

                        <x-wirechat::dropdown-link href='{{ route(WireChat::indexRouteName()) }}'>
                            {{__('wirechat.Close Chat')}}
                        </x-wirechat::dropdown-link>


                    {{-- Only show delete and clear if conversation is NOT group --}}
                    @if (!$conversation->isGroup())
                    <button class="w-full" wire:click="clearConversation"
                        wire:confirm="{{__('wirechat.Are you sure you want to clear this Chat History?')}}">

                        <x-wirechat::dropdown-link>
                            {{__('wirechat.Clear Chat History')}}
                        </x-wirechat::dropdown-link>
                    </button>

                    <button wire:click="deleteConversation"
                        wire:confirm="{{__('wirechat.Are you sure delete you want to delete Chat')}}"
                        class="w-full text-start">

                        <x-wirechat::dropdown-link class="text-red-500 dark:text-red-500">
                            {{__('wirechat.Delete Chat')}}
                        </x-wirechat::dropdown-link>

                    </button>
                    @endif


                    @if (false && $conversation->isGroup() && !auth()->user()->isOwnerOf($conversation))
                            <button wire:click="exitConversation" wire:confirm="Are you sure want to exit Group?"
                                class="w-full text-start ">

                                <x-wirechat::dropdown-link class="text-red-500 dark:text-gray-500">
                                    {{__('wirechat.Exit Group')}}
                                </x-wirechat::dropdown-link>

                            </button>
                        @endif

                    </x-slot>
                </x-wirechat::dropdown>

            </div>
        </section>


    </div>

</header>
