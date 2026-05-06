setInterval(() => {
  fetch('check_booking_status.php')
    .then(res => res.json())
    .then(data => {
      if (data.status === 'assigned' && !window.assignedNotified) {
        const box = document.getElementById('notifBox');
        box.style.display = 'block';
        window.assignedNotified = true;
        setTimeout(() => { box.style.display = 'none'; }, 6000);
      }
    });
}, 10000);
