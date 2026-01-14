// PROGRESS BAR FUNCTION
const circles = document.querySelectorAll(".circle"),
    progressBar = document.querySelector(".indicator"),
    buttons = document.querySelectorAll(".btn"),
    prevBtn = document.getElementById('prev'),
    nextBtn = document.getElementById('next');

let currentSteps = 1;
let selectedVenue = null;
let calendar = null; // Store calendar instance

// Function that updates the current step and updates the DOM
const updateSteps = (e) => {
    // Update current step based on the button clicked
    currentSteps = e.target.id === 'next' ? ++currentSteps : --currentSteps;

    // Loop through all circles and add/remove "active" class based on their index and current step
    circles.forEach((circle, index) => {
        circle.classList[`${index < currentSteps ? "add" : "remove"}`]("active");
    });

    // Update progress bar width based on current step
    progressBar.style.width = `${((currentSteps - 1) / (circles.length - 1)) * 100}%`;

    // Update content visibility
    updateContentVisibility();

    // Update button states
    updateButtonStates();
};

// Function to show/hide content based on current step
const updateContentVisibility = () => {
    const facilitySection = document.querySelector('.facility');
    const calendarWrapper = document.querySelector('.container.py-5');
const Payment = document.querySelector('.container.py-5.payment');
    if (currentSteps === 1) {
        // Show facility cards, hide calendar
        facilitySection.style.display = 'block';
        Payment.style.display = 'none';
        if (calendarWrapper) {
            calendarWrapper.style.display = 'none';
        }
    } else if (currentSteps === 2) {
        // Hide facility cards, show calendar
        facilitySection.style.display = 'none';
        Payment.style.display = 'none';
        if (calendarWrapper) {
            calendarWrapper.style.display = 'block';
            
            // Force calendar to re-render with multiple attempts
            // For render ONLY!!
            setTimeout(() => {
                if (window.fullCalendar) {
                    // Destroy and re-render
                    window.fullCalendar.render();
                }
                // Trigger window resize
                window.dispatchEvent(new Event('resize'));
            }, 50);
            
            setTimeout(() => {
                if (window.fullCalendar) {
                    window.fullCalendar.updateSize();
                }
                window.dispatchEvent(new Event('resize'));
            }, 200);
        }
    } else if (currentSteps === 3) {
        // Step 3: Show whatever content needed
        facilitySection.style.display = 'none';
        calendarWrapper.style.display = 'none';
        if (Payment) {
            Payment.style.display = 'block';
        }
        // Add your step 3 content here
    }
};

// Function to update button states
const updateButtonStates = () => {
    // Handle prev button
    if (currentSteps === 1) {
        prevBtn.disabled = true;
    } else {
        prevBtn.disabled = false;
    }

    // Handle next button
    if (currentSteps === circles.length) {
        nextBtn.disabled = true;
    } else if (currentSteps === 1 && !selectedVenue) {
        // Disable next button on step 1 if no venue selected
        nextBtn.disabled = true;
    } else {
        nextBtn.disabled = false;
    }
};

// Handle venue card selection
const venueCards = document.querySelectorAll('.col');

venueCards.forEach(card => {
    // Add click handler to the entire card column
    card.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        // Remove selected class from all cards
        venueCards.forEach(c => c.classList.remove('selected'));
        
        // Add selected class to clicked card
        card.classList.add('selected');
        
        // Store selected venue - get the second class name (chapel, basketball, hall, tennis)
        const classes = card.className.split(' ');
        selectedVenue = classes.find(c => c !== 'col' && c !== 'selected');
        
        console.log('Selected venue:', selectedVenue); // Debug log
        
        // Enable the next button now that a venue is selected
        updateButtonStates();
    });
    
    // Prevent link from navigating directly
    const link = card.querySelector('a');
    if (link) {
        link.addEventListener('click', (e) => {
            e.preventDefault();
        });
    }
});

// Add click event listeners to buttons
prevBtn.addEventListener('click', updateSteps);
nextBtn.addEventListener('click', updateSteps);

// Store calendar instance when it's created
// This should be added to your calendar.js file where you initialize FullCalendar
window.addEventListener('calendarInitialized', (e) => {
    calendar = e.detail.calendar;
});

// Initialize: disable buttons appropriately on page load and set initial visibility
updateButtonStates();
updateContentVisibility();