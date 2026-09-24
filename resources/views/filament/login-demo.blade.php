<div class="login-demo">
    <p class="login-demo-heading">Demo accounts</p>

    <p>
        Password for each account is
        <strong class="login-demo-secret">Password123</strong>
    </p>

    <ul class="login-demo-accounts">
        <li>
            <span>Super admin</span>
            <span class="login-demo-email">superadmin@example.com</span>
        </li>
        <li>
            <span>Downtown admin</span>
            <span class="login-demo-email">downtownadmin@example.com</span>
        </li>
        <li>
            <span>Westside admin</span>
            <span class="login-demo-email">westsideadmin@example.com</span>
        </li>
        <li>
            <span>Agent</span>
            <span class="login-demo-email">demo@example.com</span>
        </li>
    </ul>

    <x-filament::link
        color="gray"
        href="https://github.com/plwebdesigns/the-real-crm"
        :icon="\Filament\Support\Icons\Heroicon::CodeBracket"
        class="login-demo-github"
        rel="noopener noreferrer"
        size="sm"
        target="_blank"
    >
        View on GitHub
    </x-filament::link>
</div>

<style>
    .login-demo {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 3);
        margin-top: calc(var(--spacing) * 2);
        padding-top: calc(var(--spacing) * 5);
        border-top: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent);
        font-size: var(--text-sm);
        line-height: var(--text-sm--line-height);
        color: var(--gray-500);
    }

    .login-demo:where(.dark, .dark *) {
        color: var(--gray-400);
    }

    .login-demo-heading {
        font-weight: var(--font-weight-semibold);
        color: var(--gray-950);
    }

    .login-demo-heading:where(.dark, .dark *) {
        color: var(--color-white);
    }

    .login-demo-secret,
    .login-demo-email {
        font-family: var(--font-mono);
        font-size: var(--text-xs);
        font-weight: var(--font-weight-medium);
        color: var(--gray-950);
        overflow-wrap: anywhere;
    }

    .login-demo-secret:where(.dark, .dark *),
    .login-demo-email:where(.dark, .dark *) {
        color: var(--color-white);
    }

    .login-demo-accounts {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 1.5);
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .login-demo-accounts li {
        display: flex;
        flex-direction: column;
        gap: calc(var(--spacing) * 0.5);
    }

    .login-demo-github {
        justify-content: flex-start;
        width: fit-content;
    }
</style>
