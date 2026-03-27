(function () {
    const questionList = document.getElementById("rrp-question-list");
    const template = document.getElementById("rrp-question-template");
    const addButton = document.getElementById("rrp-add-question");

    if (!questionList || !template || !addButton) {
        return;
    }

    const updatePrimaryHidden = () => {
        questionList.querySelectorAll(".rrp-question-row").forEach((row) => {
            const radio = row.querySelector(".rrp-primary-rating-radio");
            const hidden = row.querySelector(".rrp-primary-rating-hidden");

            if (!radio || !hidden) {
                return;
            }

            hidden.value = radio.checked ? "1" : "0";
        });
    };

    const resequenceRows = () => {
        questionList.querySelectorAll(".rrp-question-row").forEach((row, index) => {
            row.dataset.index = index;
            row.querySelectorAll("input, select, textarea").forEach((field) => {
                if (!field.name) {
                    return;
                }

                field.name = field.name.replace(/questions\[[^\]]+\]/, `questions[${index}]`);
            });
            const orderField = row.querySelector('input[name*="[sort_order]"]');
            if (orderField && !orderField.value) {
                orderField.value = (index + 1) * 10;
            }
        });
        updatePrimaryHidden();
    };

    addButton.addEventListener("click", () => {
        const index = questionList.querySelectorAll(".rrp-question-row").length;
        let html = template.innerHTML.replaceAll("__index__", String(index));
        html = html.replace('value="" class="rrp-question-key"', `value="question_${Date.now()}_${index}" class="rrp-question-key"`);

        const wrapper = document.createElement("div");
        wrapper.innerHTML = html.trim();
        questionList.appendChild(wrapper.firstElementChild);
        resequenceRows();
    });

    questionList.addEventListener("click", (event) => {
        const row = event.target.closest(".rrp-question-row");
        if (!row) {
            return;
        }

        if (event.target.classList.contains("rrp-remove-question")) {
            row.remove();
            resequenceRows();
        }

        if (event.target.classList.contains("rrp-move-question-up") && row.previousElementSibling) {
            questionList.insertBefore(row, row.previousElementSibling);
            resequenceRows();
        }

        if (event.target.classList.contains("rrp-move-question-down") && row.nextElementSibling) {
            questionList.insertBefore(row.nextElementSibling, row);
            resequenceRows();
        }
    });

    questionList.addEventListener("change", (event) => {
        if (event.target.classList.contains("rrp-primary-rating-radio")) {
            updatePrimaryHidden();
        }
    });
})();
