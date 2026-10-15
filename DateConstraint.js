/**
 * Unified Date Validation Script
 * Handles both the Booking Modal and the Availability Card
 */

document.addEventListener('DOMContentLoaded', () => {
    // Select all potential Date inputs from both forms
    // Modal inputs (by name)
    const modalIn = document.querySelector('input[name="check_in"]');
    const modalOut = document.querySelector('input[name="check_out"]');
    
    // Availability Card inputs (by id)
    const cardIn = document.getElementById('check-in');
    const cardOut = document.getElementById('check-out');

    // Helper function to apply logic to a pair of inputs
    const setupDatePair = (inField, outField) => {
        if (!inField || !outField) return;

        // 1. Initialize: Set min date to today for both
        const today = new Date().toISOString().split('T')[0];
        inField.setAttribute('min', today);
        outField.setAttribute('min', today);

        // 2. Logic: When Check-In changes
        inField.addEventListener('change', () => {
            if (inField.value) {
                let nextDay = new Date(inField.value);
                nextDay.setDate(nextDay.getDate() + 1);
                const minOut = nextDay.toISOString().split('T')[0];
                
                outField.setAttribute('min', minOut);

                // Reset Check-Out if it's now invalid
                if (outField.value && outField.value <= inField.value) {
                    outField.value = '';
                }
            }
        });

        // 3. Logic: When Check-Out changes
        outField.addEventListener('change', () => {
            if (outField.value) {
                let prevDay = new Date(outField.value);
                prevDay.setDate(prevDay.getDate() - 1);
                const maxIn = prevDay.toISOString().split('T')[0];

                inField.setAttribute('max', maxIn);

                // Reset Check-In if it's now invalid
                if (inField.value && inField.value >= outField.value) {
                    inField.value = '';
                }
            }
        });
    };

    // Apply the logic to both pairs
    setupDatePair(modalIn, modalOut);
    setupDatePair(cardIn, cardOut);
});