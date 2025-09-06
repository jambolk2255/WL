/**
 * Scripts for Role-Based Income Calculator Shortcodes
 */
document.addEventListener('DOMContentLoaded', function () {

    // Handler for the [income_history_table] tabs
    window.showIncomeTab = function(tabId) {
        const wrapper = document.getElementById(tabId).closest('.income-history-wrapper');
        if (wrapper) {
            wrapper.querySelectorAll('.income-tab-content').forEach(t => t.style.display = 'none');
            wrapper.querySelectorAll('.income-tabs button').forEach(b => b.classList.remove('active'));

            const contentToShow = wrapper.querySelector('#' + tabId);
            if (contentToShow) {
                contentToShow.style.display = 'block';
            }

            // Find the button that controls this tab and activate it
            const button = Array.from(wrapper.querySelectorAll('.income-tabs button')).find(b => b.getAttribute('onclick').includes(tabId));
            if (button) {
                button.classList.add('active');
            }
        }
    }

    // Handler for the [monthly_income_history] tabs
    window.showMonthlyTab = function(tabId) {
        const wrapper = document.getElementById(tabId).closest('.monthly-income-wrapper');
        if (wrapper) {
            wrapper.querySelectorAll('.monthly-tab-content').forEach(t => t.style.display = 'none');
            wrapper.querySelectorAll('.monthly-tabs .monthly-tab-btn').forEach(b => b.classList.remove('active'));

            const contentToShow = wrapper.querySelector('#' + tabId);
            if (contentToShow) {
                contentToShow.style.display = 'block';
            }

            const button = Array.from(wrapper.querySelectorAll('.monthly-tabs .monthly-tab-btn')).find(b => b.getAttribute('onclick').includes(tabId));
            if (button) {
                button.classList.add('active');
            }
        }
    }

    // Handler for the [monthly_income_history] accordion
    window.toggleMonthlyGroup = function(headerElement) {
        const items = headerElement.nextElementSibling;
        const arrow = headerElement.querySelector('.monthly-arrow');
        if (items) {
            if (items.style.display === 'block') {
                items.style.display = 'none';
                if(arrow) arrow.textContent = '▼';
            } else {
                items.style.display = 'block';
                if(arrow) arrow.textContent = '▲';
            }
        }
    }

});
