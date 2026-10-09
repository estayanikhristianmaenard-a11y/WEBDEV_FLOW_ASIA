class StateManager {
  constructor() {
    this.state = {
      role: 'STAFF',
      activeEmployeeId: 'EMP-001',
      inspectedEmployeeId: null,
      theme: 'dark'
    };
    this.listeners = [];
  }

  getState() { return this.state; }

  subscribe(listener) {
    this.listeners.push(listener);
  }

  notify() {
    this.listeners.forEach(fn => fn(this.state));
  }

  setRole(role) {
    this.state.role = role;
    this.notify();
  }

  setInspectedEmployee(empId) {
    this.state.inspectedEmployeeId = empId;
    this.notify();
  }

  setTheme(theme) {
    this.state.theme = theme;
    this.notify();
  }
}

export const store = new StateManager();
