window.PFPasskey = (() => {
    const decode = value => {
        const normalized = value.replace(/-/g, '+').replace(/_/g, '/');
        const binary = atob(normalized.padEnd(Math.ceil(normalized.length / 4) * 4, '='));
        return Uint8Array.from(binary, char => char.charCodeAt(0)).buffer;
    };
    const encode = buffer => {
        const bytes = new Uint8Array(buffer || new ArrayBuffer(0));
        let binary = '';
        bytes.forEach(byte => binary += String.fromCharCode(byte));
        return btoa(binary);
    };
    const prepareCreate = options => {
        options.publicKey.challenge = decode(options.publicKey.challenge);
        options.publicKey.user.id = decode(options.publicKey.user.id);
        (options.publicKey.excludeCredentials || []).forEach(item => item.id = decode(item.id));
        return options;
    };
    const prepareGet = options => {
        options.publicKey.challenge = decode(options.publicKey.challenge);
        (options.publicKey.allowCredentials || []).forEach(item => item.id = decode(item.id));
        return options;
    };
    return {decode, encode, prepareCreate, prepareGet};
})();
