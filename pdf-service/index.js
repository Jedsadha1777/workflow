const express = require('express');
const puppeteer = require('puppeteer');
const { PDFDocument } = require('pdf-lib');
const app = express();

app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

let browser = null;

async function initBrowser() {
    browser = await puppeteer.launch({
        headless: 'new',
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu'
        ]
    });
    console.log('Browser initialized');
}

async function generateSingleSheetPDF(html, options) {
    const page = await browser.newPage();

    await page.setViewport({
        width: 800,
        height: 600,
        deviceScaleFactor: 1
    });

    await page.setContent(html, {
        waitUntil: 'networkidle0',
        timeout: 30000
    });

    if (options.zoom && options.zoom !== 1) {
        await page.evaluate((zoom) => {
            document.body.style.zoom = zoom;
        }, options.zoom);
    }

    const pdfOptions = {
        printBackground: true,
        margin: {
            top: 0,
            right: 0,
            bottom: 0,
            left: 0
        }
    };

    const dimensions = await page.evaluate(() => {
        const body = document.body;
        const table = document.querySelector('table');
        const firstChild = body.firstElementChild;
        
        let width, height;
        
        if (table) {
            const rect = table.getBoundingClientRect();
            width = Math.ceil(rect.width);
            height = Math.ceil(rect.height);
            return { width, height, source: 'table' };
        } else if (firstChild) {
            const rect = firstChild.getBoundingClientRect();
            width = Math.ceil(rect.width);
            height = Math.ceil(rect.height);
            return { width, height, source: 'firstChild' };
        } else {
            return { 
                width: body.scrollWidth, 
                height: body.scrollHeight,
                source: 'body'
            };
        }
    });
    
    pdfOptions.width = `${dimensions.width + 20}px`;
    pdfOptions.height = `${dimensions.height + 20}px`;
    pdfOptions.preferCSSPageSize = false;

    const pdfBuffer = await page.pdf(pdfOptions);
    await page.close();

    return { buffer: pdfBuffer, dimensions };
}

app.post('/api/generate-pdf', async (req, res) => {
    try {
        const { html, options = {} } = req.body;
 
        if (!html) {
            return res.status(400).json({ error: 'HTML is required' });
        }

        if (options.orientation === 'fit') {
            console.log('=== FIT MODE: Generating separate PDFs per sheet ===');
            
            const page = await browser.newPage();
            await page.setContent(html, { waitUntil: 'networkidle0' });
            
            const sheetsHtml = await page.evaluate(() => {
                const sheets = [];
                const body = document.body;
                const children = Array.from(body.children);
                
                let currentSheet = [];
                
                children.forEach(child => {
                    if (child.style.pageBreakBefore === 'always' || 
                        child.getAttribute('style')?.includes('page-break-before: always')) {
                        if (currentSheet.length > 0) {
                            sheets.push(currentSheet.join(''));
                            currentSheet = [];
                        }
                    } else {
                        currentSheet.push(child.outerHTML);
                    }
                });
                
                if (currentSheet.length > 0) {
                    sheets.push(currentSheet.join(''));
                }
                
                const style = document.querySelector('style')?.outerHTML || '';
                
                return sheets.map(sheetHtml => {
                    return `<!DOCTYPE html><html><head><meta charset="UTF-8">${style}</head><body>${sheetHtml}</body></html>`;
                });
            });
            
            await page.close();
            
            console.log(`Found ${sheetsHtml.length} sheets`);
            
            const pdfBuffers = [];
            for (let i = 0; i < sheetsHtml.length; i++) {
                console.log(`Generating PDF for sheet ${i + 1}...`);
                const result = await generateSingleSheetPDF(sheetsHtml[i], options);
                pdfBuffers.push(result.buffer);
                console.log(`Sheet ${i + 1}: ${result.dimensions.width}px × ${result.dimensions.height}px`);
            }
            
            const mergedPdf = await PDFDocument.create();
            
            for (const buffer of pdfBuffers) {
                const pdf = await PDFDocument.load(buffer);
                const pages = await mergedPdf.copyPages(pdf, pdf.getPageIndices());
                pages.forEach(page => mergedPdf.addPage(page));
            }
            
            const mergedPdfBytes = await mergedPdf.save();
            
            console.log('=== PDF merge completed ===');
            
            res.set({
                'Content-Type': 'application/pdf',
                'Content-Length': mergedPdfBytes.length,
                'Content-Disposition': `attachment; filename="document.pdf"`
            });
            res.send(Buffer.from(mergedPdfBytes));
            
        } else {
            const page = await browser.newPage();

            await page.setViewport({
                width: 1920,
                height: 1080,
                deviceScaleFactor: 1
            });

            await page.setContent(html, {
                waitUntil: 'networkidle0',
                timeout: 30000
            });

            if (options.zoom && options.zoom !== 1) {
                await page.evaluate((zoom) => {
                    document.body.style.zoom = zoom;
                }, options.zoom);
            }

            const pdfOptions = {
                printBackground: true,
                format: options.format || 'A4',
                landscape: options.orientation === 'landscape',
                margin: {
                    top: '5mm',
                    right: '5mm',
                    bottom: '5mm',
                    left: '5mm'
                },
                preferCSSPageSize: false
            };

            const pdfBuffer = await page.pdf(pdfOptions);
            await page.close();

            res.set({
                'Content-Type': 'application/pdf',
                'Content-Length': pdfBuffer.length,
                'Content-Disposition': `attachment; filename="document.pdf"`
            });
            res.send(pdfBuffer);
        }

    } catch (error) {
        console.error('PDF generation error:', error);
        res.status(500).json({ 
            error: 'Failed to generate PDF',
            message: error.message 
        });
    }
});

app.get('/health', (req, res) => {
    res.json({ 
        status: 'ok',
        browserActive: browser !== null 
    });
});

const PORT = process.env.PORT || 3000;

app.listen(PORT, async () => {
    console.log(`PDF Service running on port ${PORT}`);
    await initBrowser();
});

process.on('SIGTERM', async () => {
    console.log('SIGTERM received, closing browser...');
    if (browser) {
        await browser.close();
    }
    process.exit(0);
});