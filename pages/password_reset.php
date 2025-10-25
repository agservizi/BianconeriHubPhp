<?php
$tokenParam = trim((string) ($_GET['token'] ?? ''));
$storedToken = getOldInput('password_reset_token');
if ($tokenParam === '' && is_string($storedToken) && $storedToken !== '') {
    $tokenParam = $storedToken;
}

$tokenDetails = $tokenParam !== '' ? getPasswordResetTokenDetails($tokenParam) : null;
$tokenExpired = $tokenDetails ? !empty($tokenDetails['expired']) : false;
$tokenValid = $tokenDetails && !$tokenExpired;
$userSummary = $tokenValid ? ($tokenDetails['user'] ?? []) : [];
$resetDisplayName = $tokenValid ? buildUserDisplayName($userSummary['first_name'] ?? null, $userSummary['last_name'] ?? null, (string) ($userSummary['username'] ?? '')) : '';
?>
<section class="space-y-6 mx-auto max-w-3xl">
    <div class="text-center space-y-2">
        <h1 class="text-2xl font-bold">Imposta una nuova password</h1>
        <p class="text-gray-400 text-sm">Proteggi il tuo profilo scegliendo una password forte e tieni al sicuro l'accesso alla community.</p>
    </div>

    <?php if ($tokenParam === ''): ?>
        <div class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-4 text-sm text-gray-300">
            <p>Il link di reimpostazione non è valido. Richiedi nuovamente l'email per ricevere un nuovo collegamento sicuro.</p>
            <a href="?page=password_forgot" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Richiedi nuovo link</a>
        </div>
    <?php elseif (!$tokenDetails): ?>
        <div class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-4 text-sm text-gray-300">
            <p>Questo link non è più valido o è già stato utilizzato. Per motivi di sicurezza, puoi richiederne uno nuovo.</p>
            <a href="?page=password_forgot" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Invia un nuovo link</a>
        </div>
    <?php elseif ($tokenExpired): ?>
        <div class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-4 text-sm text-gray-300">
            <p>Il link è scaduto. Per reimpostare la password richiedi una nuova email e completa la procedura entro 60 minuti.</p>
            <a href="?page=password_forgot" class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-black transition-all hover:bg-juventus-silver">Richiedi nuovo link</a>
        </div>
    <?php else: ?>
        <form action="" method="post" class="bg-gray-900 p-6 rounded-2xl shadow-lg space-y-5">
            <input type="hidden" name="form_type" value="password_reset">
            <input type="hidden" name="_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenParam, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="space-y-1 text-sm text-gray-400">
                <p class="font-medium text-white">Ciao <?php echo htmlspecialchars($resetDisplayName, ENT_QUOTES, 'UTF-8'); ?>!</p>
                <p>Per completare il reset imposta una password nuova di almeno 6 caratteri.</p>
            </div>
            <div class="space-y-2">
                <label for="new-password" class="text-sm font-medium">Nuova password</label>
                <input id="new-password" name="password" type="password" placeholder="••••••" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required minlength="6">
            </div>
            <div class="space-y-2">
                <label for="new-password-confirm" class="text-sm font-medium">Conferma password</label>
                <input id="new-password-confirm" name="password_confirmation" type="password" placeholder="Ripeti password" class="w-full bg-black/70 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white placeholder:text-gray-500 focus:outline-none focus:ring-2 focus:ring-white" required minlength="6">
            </div>
            <button type="submit" class="w-full py-3 rounded-full bg-white text-black font-semibold transition-all duration-300 hover:bg-juventus-silver">Aggiorna password</button>
            <p class="text-xs text-gray-500 leading-relaxed">Suggerimento: usa una combinazione di lettere, numeri e simboli per rendere la password più sicura.</p>
        </form>
    <?php endif; ?>
</section>
