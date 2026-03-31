/**
 * Informe (impresión, envío WhatsApp, PDF) para movimientos azúcar / operaciones operador.
 * PDF operador / movimientos por operación: jsPDF + autoTable en el navegador (datos JSON).
 * Otros informes: html2pdf.js desde CDN.
 */
(function (global) {
    'use strict';

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function fechaGeneracion() {
        return new Date().toLocaleString('es-AR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function informeCss() {
        return [
            'body{margin:0;padding:0;background:#fff;}',
            '.informe-root{max-width:210mm;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;font-size:11px;background:#fff;}',
            '.informe-banda{background:linear-gradient(135deg,#1a365d 0%,#2c5282 100%);color:#fff;padding:20px 24px;margin:0;border-radius:4px 4px 0 0;}',
            '.informe-banda h1{margin:0;font-size:20px;font-weight:700;letter-spacing:2px;text-transform:uppercase;}',
            '.informe-banda .informe-sub{margin:10px 0 0;font-size:14px;font-weight:600;line-height:1.35;}',
            '.informe-meta{margin:14px 24px 8px;font-size:10px;color:#4a5568;border-bottom:2px solid #e2e8f0;padding-bottom:10px;}',
            '.informe-linea-operador{margin:0 0 12px;font-size:12px;color:#2d3748;}',
            '.informe-tabla-wrap{margin:0 24px 16px;padding:8px 0 0;}',
            'table.informe-tabla{width:100%;border-collapse:collapse;font-size:10px;}',
            'table.informe-tabla th,table.informe-tabla td{border:1px solid #cbd5e0;padding:7px 9px;vertical-align:top;}',
            'table.informe-tabla th{background:#2c5282;color:#fff;font-weight:600;}',
            'table.informe-tabla tbody tr:nth-child(even) td{background:#f7fafc;}',
            'table.informe-tabla .al-cen{text-align:center;}',
            'table.informe-tabla .al-der{text-align:right;font-family:Consolas,monospace;}',
            'table.informe-tabla .al-izq{text-align:left;}',
            '.informe-pie{margin:18px 24px 12px;padding-top:12px;border-top:1px solid #e2e8f0;font-size:9px;color:#718096;text-align:center;}',
            '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}}'
        ].join('');
    }

    /** Estilos extra para informe operaciones operador: A4, 3 columnas (Operación, Vendida a, Saldo). Sirve para PDF e impresión. */
    function informeCssOperadorPdf() {
        return [
            '@page{size:A4 portrait;margin:12mm;}',
            '.informe-root.informe-pdf-operador{max-width:794px;width:794px;box-sizing:border-box;padding:0;background:#fff;}',
            '.informe-root.informe-pdf-operador .informe-banda{padding:18px 20px;border-radius:0;}',
            '.informe-root.informe-pdf-operador .informe-meta{margin:12px 18px 10px;font-size:10px;}',
            '.informe-root.informe-pdf-operador .informe-linea-operador{margin:0 18px 14px;font-size:12px;font-weight:600;color:#1a202c;padding:10px 12px;background:#edf2f7;border-left:4px solid #2c5282;border-radius:2px;}',
            '.informe-root.informe-pdf-operador .informe-tabla-wrap{margin:0 18px 14px;padding:0;overflow:visible;}',
            '.informe-root.informe-pdf-operador table.informe-tabla{table-layout:fixed;width:100%;max-width:100%;border-collapse:collapse;font-size:11px;line-height:1.35;}',
            '.informe-root.informe-pdf-operador table.informe-tabla col.col-op{width:20%;}',
            '.informe-root.informe-pdf-operador table.informe-tabla col.col-vend{width:48%;}',
            '.informe-root.informe-pdf-operador table.informe-tabla col.col-saldo{width:32%;}',
            '.informe-root.informe-pdf-operador table.informe-tabla thead{display:table-header-group;}',
            '.informe-root.informe-pdf-operador table.informe-tabla th{font-size:11px;padding:10px 12px;text-transform:none;letter-spacing:0.02em;}',
            '.informe-root.informe-pdf-operador table.informe-tabla td{padding:8px 12px;word-wrap:break-word;overflow-wrap:break-word;word-break:break-word;}',
            '.informe-root.informe-pdf-operador table.informe-tabla th,.informe-root.informe-pdf-operador table.informe-tabla td{box-sizing:border-box;overflow:visible;}',
            '.informe-root.informe-pdf-operador table.informe-tabla tbody tr:nth-child(even) td{background:#f1f5f9;}',
            '.informe-root.informe-pdf-operador table.informe-tabla tbody tr:last-child td{background:#e2e8f0;font-weight:700;border-top:2px solid #2c5282;}',
            '.informe-root.informe-pdf-operador table.informe-tabla tbody tr:last-child td[colspan]{background:#e2e8f0;}',
            '.informe-root.informe-pdf-operador .informe-pie{margin:14px 18px 8px;font-size:9px;}',
            '@media print{',
            'html,body{margin:0;padding:0;width:100%;background:#fff;}',
            'body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}',
            '.informe-root.informe-pdf-operador{max-width:100%;width:100%;min-height:0;}',
            '.informe-root.informe-pdf-operador table.informe-tabla thead{display:table-header-group;}',
            '.informe-root.informe-pdf-operador table.informe-tabla tr{page-break-inside:avoid;}',
            '.informe-root.informe-pdf-operador .informe-banda{break-inside:avoid;}',
            '}'
        ].join('');
    }

    function esInformeOperadorWrap(wrapEl) {
        return wrapEl && wrapEl.getAttribute && wrapEl.getAttribute('data-informe-tipo') === 'operador';
    }

    function cloneTableHtml(wrapEl) {
        var wrap = wrapEl.cloneNode(true);
        wrap.querySelectorAll('a').forEach(function (a) {
            a.parentNode.replaceChild(document.createTextNode(a.textContent.trim()), a);
        });
        var extraHtml = '';
        var extraEl = wrap.querySelector('.informe-linea-operador');
        if (extraEl) {
            extraHtml = extraEl.outerHTML;
            extraEl.remove();
        }
        var t = wrap.querySelector('table');
        if (!t) {
            return extraHtml + '<p style="padding:16px;">Sin datos para el informe.</p>';
        }
        t.classList.add('informe-tabla');
        if (esInformeOperadorWrap(wrapEl)) {
            if (!t.querySelector('colgroup')) {
                var cg = document.createElement('colgroup');
                var c1 = document.createElement('col');
                c1.className = 'col-op';
                c1.style.width = '20%';
                var c2 = document.createElement('col');
                c2.className = 'col-vend';
                c2.style.width = '48%';
                var c3 = document.createElement('col');
                c3.className = 'col-saldo';
                c3.style.width = '32%';
                cg.appendChild(c1);
                cg.appendChild(c2);
                cg.appendChild(c3);
                t.insertBefore(cg, t.firstChild);
            }
            var thOp = t.querySelector('thead th');
            if (thOp && /^\s*Op\s*$/i.test((thOp.textContent || '').trim())) {
                thOp.textContent = 'Operación';
            }
        }
        return extraHtml + t.outerHTML;
    }

    function buildHtmlDocument(titulo, tableHtml, docOpts) {
        docOpts = docOpts || {};
        var rootClass = 'informe-root' + (docOpts.isOperador ? ' informe-pdf-operador' : '');
        return (
            '<div class="' + rootClass + '">' +
            '<div class="informe-banda"><h1>Informe</h1><div class="informe-sub">' + escHtml(titulo) + '</div></div>' +
            '<div class="informe-meta"><strong>Generado:</strong> ' + escHtml(fechaGeneracion()) + '</div>' +
            '<div class="informe-tabla-wrap">' + tableHtml + '</div>' +
            '<div class="informe-pie">Documento generado desde el sistema · Uso interno</div>' +
            '</div>'
        );
    }

    function informeStyleTag(docOpts) {
        var css = informeCss();
        if (docOpts && docOpts.isOperador) {
            css += informeCssOperadorPdf();
        }
        return css;
    }

    /** Formato moneda para tablas PDF (cliente). */
    function fmtMoneyPdf(n) {
        var x = Number(n) || 0;
        var sign = x < 0 ? '-' : '';
        return (
            '$ ' +
            sign +
            Math.abs(x).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        );
    }

    /** Carga jsPDF y jspdf-autotable desde CDN (una vez). */
    function cargarJspdfAutotable(callback) {
        function ready() {
            try {
                if (global.jspdf && global.jspdf.jsPDF) {
                    var d = new global.jspdf.jsPDF();
                    return typeof d.autoTable === 'function';
                }
            } catch (e) {}
            return false;
        }
        if (ready()) {
            callback(null);
            return;
        }
        if (document.querySelector('script[data-informe-autotable]')) {
            var n = 0;
            var t = setInterval(function () {
                n++;
                if (ready()) {
                    clearInterval(t);
                    callback(null);
                } else if (n > 200) {
                    clearInterval(t);
                    callback(new Error('TIMEOUT_JSPDF'));
                }
            }, 50);
            return;
        }
        function loadAutotable() {
            if (ready()) {
                callback(null);
                return;
            }
            var s2 = document.createElement('script');
            s2.setAttribute('data-informe-autotable', '1');
            s2.src =
                'https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js';
            s2.crossOrigin = 'anonymous';
            s2.onload = function () {
                if (ready()) {
                    callback(null);
                } else {
                    callback(new Error('AUTOTABLE'));
                }
            };
            s2.onerror = function () {
                callback(new Error('LOAD_AUTOTABLE'));
            };
            document.head.appendChild(s2);
        }
        if (global.jspdf && global.jspdf.jsPDF) {
            loadAutotable();
            return;
        }
        if (document.querySelector('script[data-informe-jspdf]')) {
            var n2 = 0;
            var t2 = setInterval(function () {
                n2++;
                if (global.jspdf && global.jspdf.jsPDF) {
                    clearInterval(t2);
                    loadAutotable();
                } else if (n2 > 200) {
                    clearInterval(t2);
                    callback(new Error('TIMEOUT_JSPDF'));
                }
            }, 50);
            return;
        }
        var s1 = document.createElement('script');
        s1.setAttribute('data-informe-jspdf', '1');
        s1.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
        s1.crossOrigin = 'anonymous';
        s1.onload = loadAutotable;
        s1.onerror = function () {
            callback(new Error('LOAD_JSPDF'));
        };
        document.head.appendChild(s1);
    }

    function generarBlobPdfOperadorCliente(operadorId) {
        return new Promise(function (resolve, reject) {
            cargarJspdfAutotable(function (err) {
                if (err) {
                    reject(err);
                    return;
                }
                var urlFetch = 'obtener_operaciones_operador.php?operador_id=' + encodeURIComponent(operadorId);
                fetch(urlFetch, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) {
                        if (!r.ok) {
                            return r.text().then(function () {
                                throw new Error('Error ' + r.status + ' al obtener datos del informe.');
                            });
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            throw new Error((data && data.error) || 'Sin datos del operador.');
                        }
                        var doc = new global.jspdf.jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(16);
                        doc.text('Informe', 14, 18);
                        doc.setFontSize(11);
                        doc.text('Operaciones del operador', 14, 26);
                        doc.setFontSize(9);
                        doc.setFont('helvetica', 'normal');
                        doc.text('Generado: ' + fechaGeneracion(), 14, 33);
                        doc.setFont('helvetica', 'bold');
                        doc.text('Operador: ' + (data.operador_nombre || ''), 14, 40);
                        var body = [];
                        var ops = data.operaciones || [];
                        var total = 0;
                        ops.forEach(function (op) {
                            var sal = parseFloat(op.saldo) || 0;
                            total += sal;
                            body.push([String(op.operacion), op.vendida_a || '—', fmtMoneyPdf(sal)]);
                        });
                        if (body.length) {
                            body.push([
                                {
                                    content: 'TOTAL:',
                                    colSpan: 2,
                                    styles: { halign: 'right', fontStyle: 'bold' }
                                },
                                {
                                    content: fmtMoneyPdf(total),
                                    styles: { fontStyle: 'bold', halign: 'right' }
                                }
                            ]);
                        }
                        doc.autoTable({
                            head: [['Operación', 'Vendida a', 'Saldo']],
                            body: body.length ? body : [['—', 'Sin operaciones', '—']],
                            startY: 46,
                            styles: { fontSize: 9, cellPadding: 2 },
                            headStyles: { fillColor: [44, 82, 130], textColor: 255 },
                            margin: { left: 14, right: 14 }
                        });
                        var pageCount = doc.internal.getNumberOfPages();
                        doc.setPage(pageCount);
                        doc.setFontSize(8);
                        doc.setTextColor(120);
                        doc.setFont('helvetica', 'normal');
                        doc.text(
                            'Documento generado desde el sistema · Uso interno',
                            14,
                            doc.internal.pageSize.height - 10
                        );
                        resolve(doc.output('blob'));
                    })
                    .catch(reject);
            });
        });
    }

    function generarBlobPdfMovOpCliente(operacion) {
        return new Promise(function (resolve, reject) {
            cargarJspdfAutotable(function (err) {
                if (err) {
                    reject(err);
                    return;
                }
                var urlFetch =
                    'obtener_movimientos_operacion_json.php?operacion=' + encodeURIComponent(operacion);
                fetch(urlFetch, { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) {
                        if (!r.ok) {
                            return r.text().then(function () {
                                throw new Error('Error ' + r.status + ' al obtener movimientos.');
                            });
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data || !data.ok) {
                            throw new Error((data && data.error) || 'Sin datos de la operación.');
                        }
                        var doc = new global.jspdf.jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });
                        doc.setFont('helvetica', 'bold');
                        doc.setFontSize(14);
                        doc.text('Informe', 14, 14);
                        doc.setFontSize(10);
                        doc.text('Movimientos de pago — Operación ' + String(data.operacion || operacion), 14, 22);
                        doc.setFontSize(8);
                        doc.setFont('helvetica', 'normal');
                        doc.text('Generado: ' + fechaGeneracion(), 14, 28);
                        var movs = data.movimientos || [];
                        var total = parseFloat(data.total_operacion) || 0;
                        var body = [];
                        movs.forEach(function (m) {
                            body.push([
                                m.fecha,
                                m.concepto,
                                m.comprobante,
                                m.referencia,
                                m.usuario,
                                fmtMoneyPdf(m.monto),
                                fmtMoneyPdf(m.saldo_acumulado)
                            ]);
                        });
                        if (body.length) {
                            body.push([
                                {
                                    content: 'TOTAL OPERACIÓN:',
                                    colSpan: 6,
                                    styles: { halign: 'right', fontStyle: 'bold' }
                                },
                                {
                                    content: fmtMoneyPdf(total),
                                    styles: { fontStyle: 'bold', halign: 'right' }
                                }
                            ]);
                        }
                        doc.autoTable({
                            head: [
                                [
                                    'Fecha',
                                    'Concepto',
                                    'Comprobante',
                                    'Referencia',
                                    'Usuario',
                                    'Monto',
                                    'Saldo acum.'
                                ]
                            ],
                            body: body.length
                                ? body
                                : [
                                      [
                                          '—',
                                          'Sin movimientos para esta operación',
                                          '',
                                          '',
                                          '',
                                          '',
                                          ''
                                      ]
                                  ],
                            startY: 34,
                            styles: { fontSize: 7, cellPadding: 1.5 },
                            headStyles: { fillColor: [44, 82, 130], textColor: 255 },
                            columnStyles: {
                                0: { cellWidth: 22 },
                                5: { halign: 'right', cellWidth: 26 },
                                6: { halign: 'right', cellWidth: 26 }
                            },
                            margin: { left: 14, right: 14 }
                        });
                        var pageCount = doc.internal.getNumberOfPages();
                        doc.setPage(pageCount);
                        doc.setFontSize(7);
                        doc.setTextColor(120);
                        doc.text(
                            'Documento generado desde el sistema · Uso interno',
                            14,
                            doc.internal.pageSize.height - 8
                        );
                        resolve(doc.output('blob'));
                    })
                    .catch(reject);
            });
        });
    }

    function imprimirPdfBlob(blob) {
        var u = URL.createObjectURL(blob);
        var w = window.open(u);
        if (!w) {
            URL.revokeObjectURL(u);
            alert(
                'El navegador bloqueó la ventana emergente. Permita ventanas para este sitio o use Descargar PDF e imprima desde el archivo.'
            );
            return;
        }
        var intentarPrint = function () {
            try {
                w.focus();
                if (typeof w.print === 'function') w.print();
            } catch (e) {}
        };
        try {
            w.addEventListener('load', function () {
                setTimeout(intentarPrint, 400);
            });
        } catch (e1) {}
        setTimeout(intentarPrint, 700);
        setTimeout(intentarPrint, 1800);
        setTimeout(function () {
            URL.revokeObjectURL(u);
        }, 180000);
    }

    function alertarErrorPdfServidor(err) {
        var m = err && err.message ? String(err.message) : '';
        if (m === 'LOAD_JSPDF' || m === 'LOAD_AUTOTABLE' || m === 'AUTOTABLE' || m === 'TIMEOUT_JSPDF') {
            alert(
                'No se pudo cargar el generador de PDF en el navegador. Compruebe la conexión a internet e intente de nuevo.'
            );
            return;
        }
        if (m === 'SESION') {
            alert('La sesión expiró o no tiene permiso. Inicie sesión de nuevo y vuelva a intentar.');
            return;
        }
        if (m === 'INVALID_PDF' || m === 'PDF_VACIO') {
            alert(
                'El servidor no devolvió un PDF válido. En el servidor ejecute composer install (carpeta vendor) y compruebe que no haya advertencias PHP antes del PDF.'
            );
            return;
        }
        alert(m || 'No se pudo completar la operación.');
    }

    /** Informe operaciones operador: PDF en el navegador (jsPDF + datos JSON). */
    function operadorIdDelWrapExport(wrap) {
        if (!wrap || wrap.getAttribute('data-informe-tipo') !== 'operador') return 0;
        var id = parseInt(wrap.getAttribute('data-operador-id'), 10);
        return id > 0 ? id : 0;
    }

    function azucarDescargarPdfOperadorServidor(operadorId) {
        generarBlobPdfOperadorCliente(operadorId)
            .then(function (blob) {
                var fname =
                    'operaciones_operador_' +
                    operadorId +
                    '_' +
                    new Date().toISOString().slice(0, 10) +
                    '.pdf';
                descargarBlobPdf(blob, fname);
            })
            .catch(alertarErrorPdfServidor);
    }

    function azucarImprimirPdfOperadorServidor(operadorId) {
        generarBlobPdfOperadorCliente(operadorId)
            .then(function (blob) {
                imprimirPdfBlob(blob);
            })
            .catch(function (err) {
                alertarErrorPdfServidor(err);
            });
    }

    function azucarWhatsappPdfOperadorServidor(operadorId, titulo) {
        generarBlobPdfOperadorCliente(operadorId)
            .then(function (blob) {
                var fname =
                    'operaciones_operador_' +
                    operadorId +
                    '_' +
                    new Date().toISOString().slice(0, 10) +
                    '.pdf';
                compartirPdfPorWhatsapp(blob, fname, titulo);
            })
            .catch(alertarErrorPdfServidor);
    }

    /** Movimientos de pago por operación: PDF en el navegador. */
    function esInformeMovPagoOpWrap(wrap) {
        return wrap && wrap.getAttribute('data-informe-tipo') === 'mov_pago_op';
    }

    function operacionDelWrapMovOp(wrap) {
        if (!esInformeMovPagoOpWrap(wrap)) return 0;
        var o = parseInt(wrap.getAttribute('data-operacion'), 10);
        return o > 0 ? o : 0;
    }

    function azucarDescargarPdfMovOpServidor(operacion) {
        generarBlobPdfMovOpCliente(operacion)
            .then(function (blob) {
                var fname =
                    'movimientos_operacion_' +
                    operacion +
                    '_' +
                    new Date().toISOString().slice(0, 10) +
                    '.pdf';
                descargarBlobPdf(blob, fname);
            })
            .catch(alertarErrorPdfServidor);
    }

    function azucarImprimirPdfMovOpServidor(operacion) {
        generarBlobPdfMovOpCliente(operacion)
            .then(function (blob) {
                imprimirPdfBlob(blob);
            })
            .catch(function (err) {
                alertarErrorPdfServidor(err);
            });
    }

    function azucarWhatsappPdfMovOpServidor(operacion, titulo) {
        generarBlobPdfMovOpCliente(operacion)
            .then(function (blob) {
                var fname =
                    'movimientos_operacion_' +
                    operacion +
                    '_' +
                    new Date().toISOString().slice(0, 10) +
                    '.pdf';
                compartirPdfPorWhatsapp(blob, fname, titulo);
            })
            .catch(alertarErrorPdfServidor);
    }

    function azucarImprimirInforme(wrapId, titulo) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        if (esInformeOperadorWrap(wrap)) {
            var oidImp = operadorIdDelWrapExport(wrap);
            if (oidImp > 0) {
                azucarImprimirPdfOperadorServidor(oidImp);
                return;
            }
            alert('No se identificó el operador. Cierre el informe y vuelva a abrirlo desde Operaciones del operador.');
            return;
        }
        if (esInformeMovPagoOpWrap(wrap)) {
            var opImp = operacionDelWrapMovOp(wrap);
            if (opImp > 0) {
                azucarImprimirPdfMovOpServidor(opImp);
                return;
            }
            alert('No se identificó la operación. Cierre el modal y vuelva a abrirlo desde la columna OP.');
            return;
        }
        var docOpts = { isOperador: esInformeOperadorWrap(wrap) };
        var inner = cloneTableHtml(wrap);
        var full = buildHtmlDocument(titulo, inner, docOpts);
        var iframe = document.createElement('iframe');
        iframe.setAttribute('aria-hidden', 'true');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
        document.body.appendChild(iframe);
        var doc = iframe.contentWindow.document;
        doc.open();
        doc.write(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Informe</title><style>' +
                informeStyleTag(docOpts) +
                '</style></head><body>' +
                full +
                '</body></html>'
        );
        doc.close();
        iframe.onload = function () {
            try {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            } finally {
                setTimeout(function () {
                    if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
                }, 1800);
            }
        };
    }

    function cargarHtml2Pdf(callback) {
        if (typeof global.html2pdf === 'function') {
            callback(global.html2pdf);
            return;
        }
        var existing = document.querySelector('script[data-informe-html2pdf]');
        if (existing) {
            var n = 0;
            var t = setInterval(function () {
                n++;
                if (typeof global.html2pdf === 'function') {
                    clearInterval(t);
                    callback(global.html2pdf);
                } else if (n > 200) {
                    clearInterval(t);
                    alert('No se pudo cargar el generador de PDF.');
                }
            }, 50);
            return;
        }
        var s = document.createElement('script');
        s.setAttribute('data-informe-html2pdf', '1');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        s.crossOrigin = 'anonymous';
        s.onload = function () {
            if (typeof global.html2pdf === 'function') {
                callback(global.html2pdf);
            }
        };
        s.onerror = function () {
            alert('No se pudo cargar el generador de PDF. Compruebe la conexión a internet.');
        };
        document.head.appendChild(s);
    }

    function slugNombreArchivo(base) {
        var s = String(base || 'informe').replace(/[^a-zA-Z0-9_-]+/g, '_').replace(/^_|_$/g, '');
        return s || 'informe';
    }

    /** Crea el nodo oculto con el mismo HTML que usa Descargar PDF (A4, estilos operador si aplica). */
    function armarHolderExportPdf(wrapEl, titulo) {
        var docOpts = { isOperador: esInformeOperadorWrap(wrapEl) };
        var inner = cloneTableHtml(wrapEl);
        var full = buildHtmlDocument(titulo, inner, docOpts);
        var holder = document.createElement('div');
        /* No usar left:-9999px: html2canvas recorta el ancho y pierde columnas. Fuera de vista abajo, sin opacidad (no afecta al canvas). */
        holder.style.cssText =
            'position:fixed;left:0;top:120vh;width:794px;max-width:794px;background:#fff;overflow:visible;z-index:1000;pointer-events:none;box-sizing:border-box;';
        holder.innerHTML = full;
        document.body.appendChild(holder);
        var root = holder.querySelector('.informe-root');
        return { holder: holder, root: root, docOpts: docOpts };
    }

    function opcionesHtml2Pdf(docOpts, filenameBase) {
        var isOperador = docOpts.isOperador;
        var fname = slugNombreArchivo(filenameBase) + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
        var html2canvasOpts = {
            scale: 2,
            useCORS: true,
            logging: false,
            letterRendering: true,
            windowWidth: 794,
            scrollY: 0,
            scrollX: 0,
            allowTaint: false,
            backgroundColor: '#ffffff'
        };
        if (isOperador) {
            html2canvasOpts.onclone = function (clonedDoc) {
                var root = clonedDoc.querySelector('.informe-root.informe-pdf-operador');
                if (root) {
                    root.style.width = '794px';
                    root.style.maxWidth = '794px';
                    root.style.marginLeft = '0';
                    root.style.marginRight = '0';
                    root.style.transform = 'none';
                    root.style.position = 'relative';
                    root.style.left = '0';
                    root.style.top = '0';
                }
            };
        }
        return {
            opts: {
                margin: isOperador ? [10, 12, 12, 12] : [12, 12, 14, 12],
                filename: fname,
                image: { type: 'jpeg', quality: 0.96 },
                html2canvas: html2canvasOpts,
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
                pagebreak: {
                    mode: ['css', 'legacy'],
                    avoid: isOperador ? ['tr', '.informe-banda', '.informe-linea-operador'] : ['tr', '.informe-banda']
                }
            },
            filename: fname
        };
    }

    function quitarHolderSiExiste(holder) {
        if (holder && holder.parentNode) holder.parentNode.removeChild(holder);
    }

    function descargarBlobPdf(blob, fname) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = fname;
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 2000);
    }

    /** Si el navegador permite compartir archivos, abre el selector (p. ej. WhatsApp con el PDF). Si no, descarga y abre wa.me con texto para adjuntar. */
    function compartirPdfPorWhatsapp(blob, fname, titulo) {
        var tituloLimpio = String(titulo).replace(/\*/g, '');
        var textoCorto = 'Informe: ' + tituloLimpio + '\n' + fechaGeneracion();
        var archivo;
        try {
            archivo = new File([blob], fname, { type: 'application/pdf', lastModified: Date.now() });
        } catch (e1) {
            archivo = blob;
        }

        function puedeCompartirArchivo(f) {
            try {
                if (typeof File === 'undefined' || !(f instanceof File)) {
                    return false;
                }
                return (
                    navigator.share &&
                    typeof navigator.canShare === 'function' &&
                    navigator.canShare({ files: [f] })
                );
            } catch (e2) {
                return false;
            }
        }

        function abrirWhatsappConInstruccionesAdjunto() {
            var msg =
                '*Informe (PDF)*\n\n' +
                tituloLimpio +
                '\n' +
                fechaGeneracion() +
                '\n\n' +
                'Adjunte el archivo PDF que se descargó (mismo formato que el botón Descargar PDF).';
            window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank');
        }

        if (puedeCompartirArchivo(archivo)) {
            navigator
                .share({
                    files: [archivo],
                    title: tituloLimpio,
                    text: textoCorto
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    descargarBlobPdf(blob, fname);
                    abrirWhatsappConInstruccionesAdjunto();
                });
            return;
        }

        descargarBlobPdf(blob, fname);
        abrirWhatsappConInstruccionesAdjunto();
    }

    /** Texto plano (solo si falla la generación del PDF). */
    function whatsappInformeTextoPlano(wrap, titulo) {
        var lines = [];
        lines.push('*INFORME*');
        lines.push('*' + String(titulo).replace(/\*/g, '') + '*');
        lines.push('Generado: ' + fechaGeneracion());
        lines.push('');
        var opLine = wrap.querySelector('.informe-linea-operador');
        if (opLine) {
            lines.push(opLine.textContent.replace(/\r?\n/g, ' ').replace(/\s+/g, ' ').trim());
            lines.push('');
        }
        lines.push('────────────────────────');
        var table = wrap.querySelector('table');
        if (table) {
            table.querySelectorAll('tr').forEach(function (tr) {
                var parts = [];
                tr.querySelectorAll('th, td').forEach(function (c) {
                    parts.push(c.textContent.replace(/\r?\n/g, ' ').replace(/\s+/g, ' ').trim());
                });
                lines.push(parts.join(' | '));
            });
        }
        lines.push('────────────────────────');
        var text = lines.join('\n');
        if (text.length > 4000) text = text.slice(0, 3990) + '…';
        window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank');
    }

    function azucarPdfInforme(wrapId, titulo, filenameBase) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        if (esInformeOperadorWrap(wrap)) {
            var oidPdf = operadorIdDelWrapExport(wrap);
            if (oidPdf > 0) {
                azucarDescargarPdfOperadorServidor(oidPdf);
                return;
            }
            alert('No se identificó el operador. Cierre el informe y vuelva a abrirlo desde Operaciones del operador.');
            return;
        }
        if (esInformeMovPagoOpWrap(wrap)) {
            var opPdf = operacionDelWrapMovOp(wrap);
            if (opPdf > 0) {
                azucarDescargarPdfMovOpServidor(opPdf);
                return;
            }
            alert('No se identificó la operación. Cierre el modal y vuelva a abrirlo desde la columna OP.');
            return;
        }
        var built = armarHolderExportPdf(wrap, titulo);
        if (!built.root) {
            quitarHolderSiExiste(built.holder);
            return;
        }
        cargarHtml2Pdf(function (html2pdf) {
            var po = opcionesHtml2Pdf(built.docOpts, filenameBase);
            var run = html2pdf().set(po.opts).from(built.root).save();
            if (run && typeof run.then === 'function') {
                run
                    .then(function () {
                        quitarHolderSiExiste(built.holder);
                    })
                    .catch(function () {
                        quitarHolderSiExiste(built.holder);
                        alert('No se pudo generar el PDF. Use Imprimir y elija Guardar como PDF.');
                    });
            } else {
                setTimeout(function () {
                    quitarHolderSiExiste(built.holder);
                }, 4000);
            }
        });
    }

    /**
     * Genera el mismo PDF que "Descargar PDF" y lo envía por WhatsApp:
     * en móviles con Web Share API suele abrir WhatsApp con el archivo; en PC descarga el PDF y abre WhatsApp con mensaje para adjuntarlo.
     */
    function azucarWhatsappInforme(wrapId, titulo, filenameBase) {
        var wrap = document.getElementById(wrapId);
        if (!wrap) return;
        filenameBase = filenameBase || 'informe';
        if (esInformeOperadorWrap(wrap)) {
            var oidWa = operadorIdDelWrapExport(wrap);
            if (oidWa > 0) {
                azucarWhatsappPdfOperadorServidor(oidWa, titulo);
                return;
            }
            alert('No se identificó el operador. Cierre el informe y vuelva a abrirlo desde Operaciones del operador.');
            return;
        }
        if (esInformeMovPagoOpWrap(wrap)) {
            var opWa = operacionDelWrapMovOp(wrap);
            if (opWa > 0) {
                azucarWhatsappPdfMovOpServidor(opWa, titulo);
                return;
            }
            alert('No se identificó la operación. Cierre el modal y vuelva a abrirlo desde la columna OP.');
            return;
        }
        var built = armarHolderExportPdf(wrap, titulo);
        if (!built.root) {
            quitarHolderSiExiste(built.holder);
            return;
        }
        cargarHtml2Pdf(function (html2pdf) {
            var po = opcionesHtml2Pdf(built.docOpts, filenameBase);
            var prom = html2pdf().set(po.opts).from(built.root).outputPdf('blob');
            if (!prom || typeof prom.then !== 'function') {
                quitarHolderSiExiste(built.holder);
                alert('No se pudo preparar el PDF para WhatsApp.');
                whatsappInformeTextoPlano(wrap, titulo);
                return;
            }
            prom
                .then(function (blob) {
                    quitarHolderSiExiste(built.holder);
                    compartirPdfPorWhatsapp(blob, po.filename, titulo);
                })
                .catch(function () {
                    quitarHolderSiExiste(built.holder);
                    alert('No se pudo generar el PDF. Se abrirá WhatsApp con el texto del informe.');
                    whatsappInformeTextoPlano(wrap, titulo);
                });
        });
    }

    global.azucarImprimirInforme = azucarImprimirInforme;
    global.azucarWhatsappInforme = azucarWhatsappInforme;
    global.azucarPdfInforme = azucarPdfInforme;
})(typeof window !== 'undefined' ? window : this);
