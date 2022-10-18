function copyToClipboard(elementId) {

    /* Get the text field */
    var copyText = document.getElementById(elementId);

    /* Select the text field */
    copyText.select();
    copyText.setSelectionRange(0, 99999); /* For mobile devices */

    /* Copy the text inside the text field */
    if (window.isSecureContext && navigator.clipboard) {
        navigator.clipboard
            .writeText(copyText.value)
            .then(() => {
                console.info("Successfully copied to clipboard");
            })
            .catch(() => {
                console.error("Unable to copy to clipboard");
            });
    } else {
        try {
            document.execCommand('copy');
            console.info("Successfully copied to clipboard");
        } catch (err) {
            console.error('Unable to copy to clipboard', err);
        }
    }

}