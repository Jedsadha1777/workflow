const express = require('express');
const puppeteer = require('puppeteer');
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

app.post('/api/generate-pdf', async (req, res) => {
    try {
        const { html, options = {} } = req.body;
 
        if (!html) {
            return res.status(400).json({ error: 'HTML is required' });
        }

        const page = await browser.newPage();

        if (options.orientation === 'fit') {
            await page.setViewport({
                width: 800,
                height: 600,
                deviceScaleFactor: 1
            });
        } else {
            await page.setViewport({
                width: 1920,
                height: 1080,
                deviceScaleFactor: 1
            });
        }

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
            printBackground: true
        };

        if (options.orientation === 'fit') {
            pdfOptions.margin = {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0
            };
            
            console.log('=== STARTING FIT MODE DETECTION ===');
            
            const dimensions = await page.evaluate(() => {
                const body = document.body;
                const table = document.querySelector('table');
                const firstChild = body.firstElementChild;
                
                let width, height;
                
                if (table) {
                    const rect = table.getBoundingClientRect();
                    width = Math.ceil(rect.width);
                    return { 
                        width, 
                        height: body.scrollHeight,
                        source: 'table',
                        rectWidth: rect.width,
                        offsetWidth: table.offsetWidth
                    };
                } else if (firstChild) {
                    const rect = firstChild.getBoundingClientRect();
                    width = Math.ceil(rect.width);
                    return { 
                        width, 
                        height: body.scrollHeight,
                        source: 'firstChild',
                        tag: firstChild.tagName
                    };
                } else {
                    return { 
                        width: body.scrollWidth, 
                        height: body.scrollHeight,
                        source: 'body'
                    };
                }
            });
            
            console.log('=== FIT MODE RESULT ===');
            console.log('Source:', dimensions.source);
            console.log('Width:', dimensions.width);
            console.log('Height:', dimensions.height);
            if (dimensions.rectWidth) console.log('rectWidth:', dimensions.rectWidth);
            if (dimensions.offsetWidth) console.log('offsetWidth:', dimensions.offsetWidth);
            console.log('===========================');
            
            pdfOptions.width = `${dimensions.width}px`;
            pdfOptions.height = `${dimensions.height}px`;
            pdfOptions.preferCSSPageSize = false;
        } else {
            pdfOptions.format = options.format || 'A4';
            pdfOptions.landscape = options.orientation === 'landscape';
            pdfOptions.margin = {
                top: '5mm',
                right: '5mm',
                bottom: '5mm',
                left: '5mm'
            };
            pdfOptions.preferCSSPageSize = false;
        }

        const pdfBuffer = await page.pdf(pdfOptions);

        await page.close();

        res.set({
            'Content-Type': 'application/pdf',
            'Content-Length': pdfBuffer.length,
            'Content-Disposition': `attachment; filename="document.pdf"`
        });
        res.send(pdfBuffer);

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