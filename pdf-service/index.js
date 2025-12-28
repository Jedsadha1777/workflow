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

        // ใช้ CSS เป็นตัวกำหนดขนาด - ไม่ต้องบอก viewport
        await page.setContent(html, {
            waitUntil: 'networkidle0',
            timeout: 30000
        });

        // บอก Puppeteer ให้ใช้ตาม CSS (@page) ไม่ต้องยุ่ง
        const pdfBuffer = await page.pdf({
            preferCSSPageSize: true, // สำคัญ: ใช้ @page จาก CSS
            printBackground: true,
            margin: { top: 0, right: 0, bottom: 0, left: 0 } // ใช้ margin จาก @page แทน
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