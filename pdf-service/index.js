// pdf-service/index.js
const express = require('express');
const puppeteer = require('puppeteer');
const app = express();

app.use(express.json({ limit: '50mb' }));
app.use(express.urlencoded({ limit: '50mb', extended: true }));

let browser = null;

// เปิด browser ตอน start (reuse browser instance)
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

        await page.setViewport({
            width: options.orientation === 'landscape' ? 1123 : 794,
            height: options.orientation === 'landscape' ? 794 : 1123,
            deviceScaleFactor: 1
        });

        await page.setContent(html, {
            waitUntil: 'networkidle0',
            timeout: 30000
        });

        const pdfBuffer = await page.pdf({
            format: options.format || 'A4',
            landscape: options.orientation === 'landscape',
            margin: {
                top: options.marginTop || '5mm',
                right: options.marginRight || '5mm',
                bottom: options.marginBottom || '5mm',
                left: options.marginLeft || '5mm'
            },
            printBackground: true,
            preferCSSPageSize: false
        });

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