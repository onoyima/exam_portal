const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('seb', {
  requestExit: () => ipcRenderer.send('seb-exit'),
  getConfig: () => ipcRenderer.invoke('seb-config'),
  onLogout: (callback) => {
    ipcRenderer.on('seb-logout', () => callback());
  },
});
