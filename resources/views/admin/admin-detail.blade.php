@extends('layouts.admin-app')

@section('css')
    @vite(['resources/css/admin/admin-detail.css'])
@endsection

@section('content')
    <div class="detail__content">
        <div class="detail__header">
            <h1 class="content__header--item">勤怠詳細</h1>
        </div>

        {{-- 要件5: 修正完了メッセージ（位置をボタン付近から最上部へ見やすく移動、文言を要件通りに調整） --}}
        @if (session('success'))
            <div class="alert alert-success" style="color: green; font-weight: bold; margin-bottom: 15px; padding: 10px; background-color: #e6ffe6; border: 1px solid green; border-radius: 5px;">
                修正が反映されました
            </div>
        @endif

        {{-- 要件2: 承認待ちメッセージ（一番気付きやすいようにフォームの上に配置） --}}
        @if (isset($attendanceRecord['has_pending_application']) && $attendanceRecord['has_pending_application'])
            <p class="error-message" style="color: red; font-weight: bold; margin-bottom: 15px; font-size: 16px;">
                承認待ちのため修正はできません。
            </p>
        @endif

        <form class="form" action="{{ url('/admin/attendance/' . $attendanceRecord['id']) }}" method="post">
            @csrf
            @method('PATCH')
            @php
                $hasPending = $attendanceRecord['has_pending_application'] ?? false;
                $isDisabled = $hasPending ? 'disabled' : ''; // 👈 この変数を下の入力欄に埋め込みます
            @endphp

            <div class="form__content">
                <div class="form__group">
                    <label class="form__header" for="name">名前</label>
                    <div class="form__input-group form__input-group--name">
                        <input class="form__input--name" id="name" type="text" name="name" value="{{ $user->name }}"
                                readonly>
                    </div>
                </div>
                <div class="form__group">
                    <label class="form__header">日付</label>
                    <div class="form__input-group">
                        <input class="form__input form__input--date" type="text" value="{{ $attendanceRecord['year'] }}"
                            readonly>
                        <input class="form__input form__input--date" type="text" name="new_date"
                            value="{{ $attendanceRecord['date'] }}" readonly>
                    </div>
                </div>

                <div class="form__group">
                    <label class="form__header" for="new_clock_in">出勤・退勤</label>
                    <div class="form__input-group">
                        {{-- 💡 修正：末尾に {{ $isDisabled }} を追記 --}}
                        <input class="form__input" id="new_clock_in" type="text" name="new_clock_in"
                            value="{{ old('new_clock_in', $attendanceRecord['clock_in']) }}" {{ $isDisabled }}>
                        <p>〜</p>
                        {{-- 💡 修正：末尾に {{ $isDisabled }} を追記 --}}
                        <input class="form__input" type="text" name="new_clock_out"
                            value="{{ old('new_clock_out', $attendanceRecord['clock_out']) }}" {{ $isDisabled }}>
                    </div>
                </div>

                <div class="error-message">
                    <div></div>
                    <div class="error-message__item">
                        @error('new_clock_in')
                            {{ $message }}
                        @enderror
                        @error('new_clock_out')
                            {{ $message }}
                        @enderror
                    </div>
                </div>

                @php
                    $breaks = isset($attendanceRecord['breaks']) && is_array($attendanceRecord['breaks'])
                        ? $attendanceRecord['breaks']
                        : [];
                @endphp
                @foreach ($breaks as $index => $break)
                    <div class="form__group">
                        <label class="form__header">{{ $index === 0 ? '休憩' : '休憩' . ($index + 1) }}</label>
                        <div class="form__input-group">
                            {{-- 💡 修正：末尾に {{ $isDisabled }} を追記 --}}
                            <input class="form__input" type="text" name="new_break_in[{{ $index }}]"
                                value="{{ old('new_break_in.' . $index, $break['break_in'] ?? '') }}" {{ $isDisabled }}>
                            <p>〜</p>
                            {{-- 💡 修正：末尾に {{ $isDisabled }} を追記 --}}
                            <input class="form__input" type="text" name="new_break_out[{{ $index }}]"
                                value="{{ old('new_break_out.' . $index, $break['break_out'] ?? '') }}" {{ $isDisabled }}>
                        </div>
                    </div>
                    <div class="error-message">
                        <div></div>
                        <div class="error-message__item">
                            @error('new_break_in.' . $index)
                                <p>{{ $message }}</p>
                            @enderror
                            @error('new_break_out.' . $index)
                                <p>{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endforeach

                {{-- 新しい休憩を追加できるよう末尾に空欄を1つ用意 --}}
                @php $newBreakIndex = count($breaks); @endphp
                <div class="form__group">
                    <label class="form__header">{{ $newBreakIndex === 0 ? '休憩' : '休憩' . ($newBreakIndex + 1) }}</label>
                    <div class="form__input-group">
                        {{-- 💡 修正：old関数の追加と、末尾に {{ $isDisabled }} を追記 --}}
                        <input class="form__input" type="text" name="new_break_in[{{ $newBreakIndex }}]"
                            value="{{ old('new_break_in.' . $newBreakIndex, '') }}" {{ $isDisabled }}>
                        <p>〜</p>
                        {{-- 💡 修正：old関数の追加と、末尾に {{ $isDisabled }} を追記 --}}
                        <input class="form__input" type="text" name="new_break_out[{{ $newBreakIndex }}]"
                            value="{{ old('new_break_out.' . $newBreakIndex, '') }}" {{ $isDisabled }}>
                    </div>
                </div>
                <div class="error-message">
                    <div></div>
                    <div class="error-message__item">
                        @error('new_break_in.' . $newBreakIndex)
                            <p>{{ $message }}</p>
                        @enderror
                        @error('new_break_out.' . $newBreakIndex)
                            <p>{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="form__group">
                    <label class="form__header" for="comment">備考</label>
                    <div class="form__input-group">
                        {{-- 💡 修正：old関数の追加（要件6）と、末尾に {{ $isDisabled }} を追記 --}}
                        <textarea class="form__textarea" name="comment" id="comment" {{ $isDisabled }}>{{ old('comment', $attendanceRecord['comment'] ?? '') }}</textarea>
                    </div>
                </div>
                <div class="error-message">
                    <div></div>
                    <div class="error-message__item">
                        @error('comment')
                            {{ $message }}
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form__button">
                {{-- 承認待ち（hasPending）でない時だけ「修正」ボタンを表示する（要件2） --}}
                @if(!$hasPending)
                    <button class="form__button--submit" type="submit">修正</button>
                @endif

                {{-- 一括エラーメッセージ表示（要件3） --}}
                @if ($errors->any())
                    <div class="alert alert-danger" style="color: red; margin-top: 15px; text-align: left;">
                        <ul style="margin: 0; padding-left: 20px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </form>
    </div>
@endsection

