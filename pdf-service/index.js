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
            printBackground: true
        };

        // ถ้าเป็น fit mode ให้ใช้ CSS กำหนดขนาด
        if (options.orientation === 'fit') {
            // ไม่ใส่ margin เพื่อให้เต็มจอ
            pdfOptions.margin = {
                top: 0,
                right: 0,
                bottom: 0,
                left: 0
            };
            
            // ให้ Puppeteer detect ขนาดจาก content
            const dimensions = await page.evaluate(() => {
                // หาขนาดที่ใหญ่ที่สุดจากทุก property
                const body = document.body;
                const html = document.documentElement;
                
                const width = Math.max(
                    body.scrollWidth,
                    body.offsetWidth,
                    html.clientWidth,
                    html.scrollWidth,
                    html.offsetWidth
                );
                
                const height = Math.max(
                    body.scrollHeight,
                    body.offsetHeight,
                    html.clientHeight,
                    html.scrollHeight,
                    html.offsetHeight
                );
                
                return { width, height };
            });
            
            console.log('Fit mode dimensions:', dimensions);
            
            // กำหนดขนาดกระดาษตาม content พอดี
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