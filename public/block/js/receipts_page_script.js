document.addEventListener("DOMContentLoaded", function () {
    const isPaymentIframe = document.querySelector(".payment_iframe_container");
    const iframe_targets = [".payment_iframe_container", ".payment_iframe_overlay", "body"];
    const countDownTimerElement = document.querySelector(".tp_mercury_timer");
    if (isPaymentIframe) {
        document.getElementById("silentPost").submit();
    };
    function paymentFormSubmit() {
        if (isPaymentIframe) {
            add_click_events();
            toggle_iframe();
        } else {
            document.getElementById("silentPost").submit();
        }
    } let countRemaining = 3;
    let countdownTimer = setInterval(() => {
        if (countRemaining <= 0) {
            clearInterval(countdownTimer);
            paymentFormSubmit();
        };
        countDownTimerElement.textContent = countRemaining;
        countRemaining -= 1;
    }, 1000);
    function toggle_iframe() {
        for (let x = 0;x < iframe_targets.length;x++) {
            let current_target = document.querySelector(iframe_targets[x]);
            current_target.classList.toggle("iframe_active");
            if (current_target != "body") {
                current_target.classList.toggle("iframe_inactive");
            }
        }
    };
    function add_click_events() {
        const click_events_targets = [".payment_iframe_hide_btn", ".tp_mercury_countdown_text"];
        for (let x = 0;x < click_events_targets.length;x++) {
            document.querySelector(click_events_targets[x]).addEventListener("click", function () { toggle_iframe() });
        }
            let text_output = document.querySelector(".tp_mercury_countdown_text");
            text_output.textContent = text_output.textContent.replace("Payment Form Will Show In 1 seconds", "Click Here To Re-Open The Payment Form");
            text_output.classList.add("payment_iframe_hide_btn");
    };
});
