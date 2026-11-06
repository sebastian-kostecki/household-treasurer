import { onMounted, ref } from 'vue';
import {
    readYearElapsedMark,
    writeYearElapsedMark,
} from '@/lib/year-elapsed-mark-storage';

export function useYearElapsedMark() {
    const showYearElapsedMark = ref(true);

    onMounted(() => {
        showYearElapsedMark.value = readYearElapsedMark();
    });

    function setYearElapsedMark(value: boolean): void {
        showYearElapsedMark.value = value;
        writeYearElapsedMark(value);
    }

    return { showYearElapsedMark, setYearElapsedMark };
}
