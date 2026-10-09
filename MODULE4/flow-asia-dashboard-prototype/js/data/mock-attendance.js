export const mockAttendanceData = {
  'EMP-001': {
    name: 'Maria Santos',
    role: 'Creative Designer',
    avatar: 'MS',
    kpis: { present: 18, late: 1, absent: 1, leaveUsed: 2 },
    calendar: {
      1: { type: 'P', name: 'Present', shift: '09:00 AM - 06:00 PM', time: '08:52 AM / 06:01 PM', late: 0, ot: 0, report: true },
      2: { type: 'A', name: 'Absent', shift: '09:00 AM - 06:00 PM', time: 'N/A', late: 0, ot: 0, report: false },
      5: { type: 'INV', name: 'Invalid (Rejected Selfie)', shift: '09:00 AM - 06:00 PM', time: '09:02 AM / --', late: 0, ot: 0, report: false, rejectionReason: 'CR1 Violation: Face unverified in selfie punch review by HR Admin.' },
      8: { type: 'L', name: 'Late', shift: '09:00 AM - 06:00 PM', time: '09:15 AM / 06:00 PM', late: 15, ot: 0, report: true },
      12: { type: 'LV', name: 'Approved Leave', shift: 'Vacation Leave', time: 'N/A', late: 0, ot: 0, report: false },
      13: { type: 'LV', name: 'Approved Leave', shift: 'Vacation Leave', time: 'N/A', late: 0, ot: 0, report: false },
      20: { type: 'LV', name: 'Approved Leave', shift: 'Vacation Leave', time: 'N/A', late: 0, ot: 0, report: false },
      25: { type: 'OT', name: 'Overtime Shift', shift: '09:00 AM - 09:00 PM', time: '08:55 AM / 09:05 PM', late: 0, ot: 3.0, report: true },
      31: { type: 'H', name: 'Holiday', shift: 'National Holiday', time: 'N/A', late: 0, ot: 0, report: false, holidayName: 'All Saints Day Eve' }
    }
  },
  'EMP-002': {
    name: 'Juan Dela Cruz',
    role: 'Production Lead',
    avatar: 'JC',
    kpis: { present: 19, late: 2, absent: 0, leaveUsed: 1 },
    calendar: {
      3: { type: 'L', name: 'Late', shift: '08:00 AM - 05:00 PM', time: '08:20 AM / 05:00 PM', late: 20, ot: 0, report: true },
      14: { type: 'LV', name: 'Approved Leave', shift: 'Vacation Leave', time: 'N/A', late: 0, ot: 0, report: false },
      15: { type: 'LV', name: 'Approved Leave', shift: 'Vacation Leave', time: 'N/A', late: 0, ot: 0, report: false }
    }
  }
};
