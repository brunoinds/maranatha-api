<template>
    <div>
        <react :component="component" :message="message" />
    </div>
</template>

<script>
import { ReactWrapper } from 'vuera'
import ReactImagePerspectiveCropper from 'react-image-perspective-cropper';
import { defineComponent } from 'vue';


export default defineComponent({
    name: 'ReactCropper',
    components: { react: ReactWrapper },
	props: ['emitter'],
    setup (props) {
        return {
            component: ReactImagePerspectiveCropper,
            message: 'Hello from Vue!'
        }
    }
});
</script>