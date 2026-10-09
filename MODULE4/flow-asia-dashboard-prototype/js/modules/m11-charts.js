export function initCharts() {
  if (typeof Chart === 'undefined') {
    throw new Error('Chart.js failed to load. Check the network connection and CDN availability.');
  }

  Chart.defaults.color = '#A1A1AA';
  Chart.defaults.font.family = 'Manrope';

  new Chart(document.getElementById('deptHeadcountChart'), {
    type: 'bar',
    data: {
      labels: ['Production', 'Creative', 'Events', 'Talent', 'Marketing', 'Finance', 'IT', 'HR'],
      datasets: [{ label: 'Employees', data: [34, 22, 20, 18, 14, 8, 7, 5], backgroundColor: '#B8FF2C', borderRadius: 4 }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: { x: { grid: { display: false } }, y: { grid: { color: '#2A2A2E' } } }
    }
  });

  new Chart(document.getElementById('attendanceTrendChart'), {
    type: 'line',
    data: {
      labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
      datasets: [
        { label: 'Present %', data: [94, 91, 88, 92], borderColor: '#B8FF2C', backgroundColor: 'rgba(184, 255, 44, 0.1)', fill: true, tension: 0.3 },
        { label: 'Late Count', data: [4, 7, 9, 3], borderColor: '#FFB800', tension: 0.3 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { x: { grid: { display: false } }, y: { grid: { color: '#2A2A2E' } } }
    }
  });

  new Chart(document.getElementById('mlForecastChart'), {
    type: 'line',
    data: {
      labels: ['Oct 1', 'Oct 5', 'Oct 10', 'Oct 15 (Est)', 'Oct 20 (Est)', 'Oct 25 (Est)'],
      datasets: [
        { label: 'Actual Late Rate', data: [3, 5, 8, null, null, null], borderColor: '#F4F4F5', borderWidth: 2 },
        { label: 'ML Projected Late Rate (CR2)', data: [null, null, 8, 9.2, 11.5, 7.8], borderColor: '#B8FF2C', borderDash: [5, 5], borderWidth: 2 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      scales: { x: { grid: { display: false } }, y: { grid: { color: '#2A2A2E' } } }
    }
  });
}
