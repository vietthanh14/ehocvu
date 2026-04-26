var APP_CONFIG = {
  FOLDER_ID: '1RoYbZk4KxGnuWjEYPKqP4uVU9-bEoGC2',
  TYPES: {
    BAO_LUU: {
      id: 'baoluu',
      name: 'Bảo lưu',
      templateId: '1bdFxWjJ6-d-mtWLFFU-zQNSNFhATnuxSff4Lm58RVUU',
      sheetFilter: function(loaiYc) { 
        return loaiYc.indexOf('bảo lưu') !== -1 && loaiYc.indexOf('tiếp tục học') === -1; 
      }
    },
    TIEP_TUC_HOC: {
      id: 'tieptuchoc',
      name: 'Tiếp tục học',
      templateId: '14zy8LgRJsT6VTVLz4_JRjmcIxC2JW7rLb4swiCGRghY',
      sheetFilter: function(loaiYc) { 
        return loaiYc.indexOf('tiếp tục học') !== -1; 
      }
    }
  }
};
