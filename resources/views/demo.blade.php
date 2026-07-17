<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Hub Demo Checkout</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 32px; color: #f8fafc; background: radial-gradient(circle at top, #202a3a, #090d14 62%); }
        .shell { width: min(1020px, 100%); display: grid; grid-template-columns: .9fr 1.1fr; overflow: hidden; border: 1px solid #2b3648; border-radius: 24px; background: #111722; box-shadow: 0 30px 90px #0009; }
        .intro { padding: 52px; background: linear-gradient(145deg, #e43b45, #a9142b); }
        .badge { display: inline-flex; padding: 7px 11px; border: 1px solid #ffffff55; border-radius: 999px; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 28px 0 14px; font-size: clamp(36px, 5vw, 58px); line-height: 1; }
        .intro p { color: #ffe8eb; line-height: 1.65; }
        .providers { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 34px; }
        .providers span { padding: 7px 10px; border-radius: 8px; background: #51081855; font-size: 12px; }
        .form { padding: 48px; }
        .form h2 { margin: 0 0 8px; font-size: 25px; }
        .hint { margin: 0 0 30px; color: #94a3b8; font-size: 14px; }
        label { display: block; margin: 18px 0 7px; color: #cbd5e1; font-size: 13px; font-weight: 700; }
        input, select { width: 100%; min-height: 48px; padding: 0 14px; color: #f8fafc; border: 1px solid #334155; border-radius: 10px; outline: none; background: #0b111b; font: inherit; }
        input:focus, select:focus { border-color: #ef4754; box-shadow: 0 0 0 3px #ef475422; }
        .row { display: grid; grid-template-columns: 1fr 130px; gap: 14px; }
        button { width: 100%; margin-top: 28px; min-height: 52px; border: 0; border-radius: 11px; color: white; background: #e43b45; font: inherit; font-weight: 800; cursor: pointer; }
        button:hover { background: #f04b56; }
        .errors { margin: 0 0 20px; padding: 13px 15px; color: #fecaca; border: 1px solid #7f1d1d; border-radius: 10px; background: #450a0a88; font-size: 13px; }
        .warning { margin-top: 18px; color: #64748b; font-size: 11px; text-align: center; }
        @media (max-width: 760px) { .shell { grid-template-columns: 1fr; } .intro, .form { padding: 32px; } }
    </style>
</head>
<body>
<main class="shell">
    <section class="intro">
        <span class="badge">Laravel Payment Hub</span>
        <h1>Test every gateway.</h1>
        <p>Select a provider, enter a test amount, and continue to its hosted payment page. No card data touches your Laravel application.</p>
        <div class="providers">
            @foreach ($providers as $name)
                <span>{{ $name }}</span>
            @endforeach
        </div>
    </section>

    <section class="form">
        <h2>Example payment</h2>
        <p class="hint">Use sandbox credentials while testing.</p>

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('payment-hub.demo.pay') }}">
            @csrf
            <label for="provider">Payment provider</label>
            <select id="provider" name="provider" required>
                @foreach ($providers as $value => $name)
                    <option value="{{ $value }}" @selected(old('provider', $defaultProvider) === $value)>{{ $name }}</option>
                @endforeach
            </select>

            <div class="row">
                <div>
                    <label for="amount">Amount</label>
                    <input id="amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount', '49.90') }}" required>
                </div>
                <div>
                    <label for="currency">Currency</label>
                    <input id="currency" name="currency" value="{{ old('currency', 'EUR') }}" maxlength="3" required>
                </div>
            </div>

            <label for="order_id">Order ID <small>(optional)</small></label>
            <input id="order_id" name="order_id" value="{{ old('order_id') }}" placeholder="Generated automatically">

            <label for="description">Description</label>
            <input id="description" name="description" value="{{ old('description', 'Demo order') }}">

            <button type="submit">Continue to payment</button>
        </form>
        <p class="warning">Demo route — disable PAYMENT_HUB_DEMO in production.</p>
    </section>
</main>
</body>
</html>
