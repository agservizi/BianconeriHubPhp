<?php
$oldEmail = getOldInput('forgot_email');
?>
<section class="space-y-6 mx-auto max-w-3xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Recupera la tua password</h1>
        <p class="text-gray-400 text-sm">Inserisci l'indirizzo email del tuo account e ti invieremo un link per reimpostare la password.</p>
    </div>
    <form action="" method="post" class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-5">
        <input type="hidden" name="form_type" value="forgot_password">
        <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="space-y-2">
            <label for="forgot-email" class="text-sm font-medium">Email</label>
            <input
                id="forgot-email"
                name="email"
                type="email"
                value="<?php echo htmlspecialchars((string) $oldEmail, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="tu@esempio.com"
                class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white"
                required
                autofocus
            >
        </div>
        <button type="submit" class="w-full py-3 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">Invia link di reset</button>
        <p class="text-sm text-center text-gray-400">
            Hai già ricordato la password?
            <a href="?page=login" class="text-white underline">Torna al login</a>
        </p>
    </form>
</section>
