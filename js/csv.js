/* global F1 */

/* csv.js */

(function(F1) {

  /**
   * CsvExporter Class Usage Examples.
   * 
   * Example 1: Exporting data from an array of objects:
   * 
   * const data = [
   *     { 'No': '1.', 'Trade Date': '2023-01-17', 'Trade ID': '298992', 'Type': 'SDA' },
   *     { 'No': '2.', 'Trade Date': '2023-01-18', 'Trade ID': '299333', 'Type': 'SDA' }
   * ];
   * const arrayExporter = new CsvExporter('ObjectArray', data);
   * arrayExporter.downloadCSV('array_export.csv');
   * 
   * Example 2: Exporting data from a table element:
   * 
   * const tableElement = document.querySelector('.related-trades table');
   * const tableExporter = new CsvExporter('TableElement', tableElement);
   * tableExporter.downloadCSV('table_export.csv');
   * 
   */

  class CsvExporter {
    constructor(dataType, srcObject) {
      this.dataParser = new parserRegistry[dataType](srcObject); }
    convertToCSV(data) {
      return data.map(row => row.map(cell => `"${cell}"`).join(',')).join('\n'); }
    downloadCSV(fileName = 'export.csv', data = this.dataParser.getData()) {
      const csvContent = this.convertToCSV(data);
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const downloadLink = document.createElement('a');
      downloadLink.href = url;
      downloadLink.download = fileName;
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
    }
  }

  class ObjectArrayParser {
    constructor(objArray) {
      this.objArray = objArray; }
    getData() {
      const headers = this.objArray.length ? Object.keys(this.objArray[0]) : [];
      return [headers, ...this.objArray.map(obj => headers.map(header => obj[header] || ""))];
    }
  }

  class TableElementParser {
    constructor(tableElement) {
      this.tableElement = tableElement; }
    getData() {
      return Array.from(this.tableElement.querySelectorAll('tr')).map(row => 
        Array.from(row.querySelectorAll('th, td')).flatMap(cell => {
          // Link content
          const link = cell.querySelector('a');
          const text = link ? link.textContent.trim() : cell.textContent.trim();
          // Json content
          let contents = [text.startsWith("{") ? Object.entries(JSON.parse(text)).map(([k, v]) => `${k}:${v}`).join(' | ') : text];
          // Colspans
          if (cell.getAttribute('colspan')) contents = contents.concat([...Array(cell.getAttribute('colspan') - 1)].map(() => ""));
          return contents;
        })
      );
    }
  }

  const parserRegistry = {
    'TableElement': TableElementParser,
    'ObjectArray': ObjectArrayParser
    // ... any other parsers
  };

  F1.lib = F1.lib || {};
  F1.lib.CsvExporter = CsvExporter;

})(window.F1 = window.F1 || {});  