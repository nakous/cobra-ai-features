jQuery(document).ready(function ($) {
    // Close modal when clicking the close button
    $('.close-modal').on('click', function () {
        $('#feature-help-modal').fadeOut(200);
    });

    // Close modal when clicking outside
    $(document).on('click', '.cobra-modal', function (e) {
        if ($(e.target).hasClass('cobra-modal')) {
            $(this).fadeOut(200);
        }
    });

    // Prevent modal from closing when clicking inside modal content
    $('.cobra-modal-content').on('click', function (e) {
        e.stopPropagation();
    });

});