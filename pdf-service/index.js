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
            width: options.orientation === 'landscape' ? 1123 : 794,
            height: options.orientation === 'landscape' ? 794 : 1123,
            deviceScaleFactor: 1
        });

        await page.setContent(html, {
            waitUntil: 'networkidle0',
            timeout: 30000
        });

        // วัด intrinsic width จาก browser layout จริง
        await page.evaluate((orientation) => {
            const pageWidth = orientation === 'landscape' ? 1085 : 756;
            
            const wrappers = document.querySelectorAll('.sheet-scale');
            wrappers.forEach(wrapper => {
                const table = wrapper.querySelector('table');
                if (!table) return;
                
                // 1. วัด intrinsic width จาก colgroup
                const cols = table.querySelectorAll('colgroup col');
                let intrinsicWidth = 0;
                cols.forEach(col => {
                    const w = parseFloat(col.style.width || col.getAttribute('width') || 0);
                    intrinsicWidth += w;
                });
                
                if (intrinsicWidth === 0) {
                    intrinsicWidth = table.offsetWidth;
                }
                
                // 2. Lock table width = intrinsic width
                table.style.width = intrinsicWidth + 'px';
                table.style.minWidth = intrinsicWidth + 'px';
                table.style.tableLayout = 'fixed';
                
                // 3. Lock wrapper width = intrinsic width
                wrapper.style.width = intrinsicWidth + 'px';
                
                // 4. Scale ถ้าเกิน
                if (intrinsicWidth > pageWidth) {
                    const scale = pageWidth / intrinsicWidth;
                    
                    wrapper.style.transformOrigin = 'top left';
                    wrapper.style.transform = `scale(${scale})`;
                    
                    // ปรับกรอบหลัง scale
                    wrapper.style.width = (intrinsicWidth * scale) + 'px';
                    wrapper.style.height = (table.offsetHeight * scale) + 'px';
                }
                
                // 5. Center ด้วย flexbox (parent .sheet-wrapper)
            });
        }, options.orientation);

        const pdfBuffer = await page.pdf({
            format: options.format || 'A4',
            landscape: options.orientation === 'landscape',
            margin: {
                top: '5mm',
                right: '5mm',
                bottom: '5mm',
                left: '5mm'
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