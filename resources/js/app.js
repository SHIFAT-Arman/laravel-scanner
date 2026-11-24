import { BrowserQRCodeReader } from '@zxing/browser';
import { DecodeHintType } from '@zxing/library';

const hints = new Map([[DecodeHintType.TRY_HARDER, true]]);

document.addEventListener('alpine:init', () => {
    Alpine.data('qrScanner', () => ({
        controls: null,
        error: '',
        scanning: false,

        async start() {
            this.error = '';
            this.scanning = true;
            await this.$nextTick();

            try {
                this.controls = await new BrowserQRCodeReader(hints).decodeFromConstraints(
                    {
                        video: {
                            facingMode: { ideal: 'environment' },
                            height: { ideal: 720 },
                            width: { ideal: 1280 },
                        },
                    },
                    this.$refs.video,
                    async (result, error, controls) => {
                        if (!result) return;

                        controls.stop();
                        this.controls = null;
                        this.scanning = false;
                        await this.$wire.lookup(result.getText());
                    },
                );
            } catch (error) {
                this.scanning = false;
                this.error = error.name === 'NotAllowedError'
                    ? 'Camera permission was denied. Allow camera access or enter the UUID below.'
                    : 'No camera is available. Enter the UUID below.';
            }
        },

        stop() {
            this.controls?.stop();
            this.controls = null;
            this.scanning = false;
        },

        destroy() {
            this.stop();
        },
    }));
});
