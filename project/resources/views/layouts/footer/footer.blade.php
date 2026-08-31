<footer>
    <p class="text-start">© 2022 Arar Financiera - IP: {{ $_SERVER["REMOTE_ADDR"] }}</p>
    <form name="form_reloj" id="form-reloj">
        <i class="fas fa-clock"></i>
        <input type="text" name="reloj" id="reloj" size="45" onfocus="document.querySelector('#form_reloj #reloj').blur()">
    </form>
</footer>