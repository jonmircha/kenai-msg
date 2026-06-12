<form action="<?= htmlspecialchars($site['url'] ?? '', ENT_QUOTES) ?>/busqueda" method="GET" class="search-form">
    <input type="search" name="q" placeholder="Buscar en el sitio..."
        value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES) ?>" maxlength="100" required aria-label="Buscar">
    <button type="submit">🔍</button>
</form>