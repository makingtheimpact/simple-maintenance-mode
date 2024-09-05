// JavaScript for Simple Maintenance Mode Plugin - maintenance-mode.js

// Function to copy the bypass URL
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        alert("Copied to clipboard: " + text); // Alert the copied text (or use a more subtle notification)
    })
    .catch(function(error) {
        alert("Copy failed! " + error);
    });
}

// Add click event listener to the copy button
document.addEventListener('DOMContentLoaded', function() {
    var copyButton = document.getElementById('copyBypassUrl');
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            var bypassUrl = document.getElementById('bypass_url').value;
            copyToClipboard(bypassUrl);
        });
    }
});
