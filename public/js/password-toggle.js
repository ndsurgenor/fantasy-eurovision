function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    btn.querySelector('.eye-open').classList.toggle('hidden', !showing);
    btn.querySelector('.eye-closed').classList.toggle('hidden', showing);
}
